# Hyper-V + Ubuntu 22.04.5 LTS: Zonclave Setup

Complete, start-to-finish steps for standing up Zonclave inside a Hyper-V VM running Ubuntu 22.04.5 LTS on a Windows Server host, ending with a working panel + FreeRADIUS reachable on the flat LAN at `192.168.1.250`.

This document and `install-ubuntu22.04.sh` are the officially supported installer path (CLAUDE.md Section 24.4, ADR 0003) - the one actually running the Office SancoMedia Kelder deployment (Section 26). An earlier Ubuntu 24.04 script was removed from the repo when this decision reverted; see ADR 0003 if 24.04 support is ever needed again.

**Note on the example IP below:** `192.168.1.250` here is the recommended pattern - a static address deliberately chosen outside the DHCP pool. The actual Kelder deployment ended up on `192.168.1.174`/`192.168.1.175` instead (inside the pool, requiring static DHCP mappings as a workaround - see CLAUDE.md Section 3.4). Follow this guide's `.250`-style pattern for Location 2 and Location 3 rather than repeating that.

If your VM already exists with Ubuntu installed, skip to whichever section matches where you actually are - each section is self-contained.

---

## 0. What you end up with

- A Hyper-V VM running Ubuntu 22.04.5 LTS, bridged directly onto the real LAN (192.168.1.0/24) - not Hyper-V's NAT'd default switch.
- A static IP: `192.168.1.250` (outside the existing DHCP pool, `192.168.1.10`-`192.168.1.245`).
- PHP 8.3 (via the `ondrej/php` PPA, since 22.04's default repos only ship 8.1).
- PostgreSQL, FreeRADIUS, nginx, and the Zonclave panel, all provisioned by `install-ubuntu22.04.sh`.

---

## 1. Enable Hyper-V on the Windows Server host

Elevated PowerShell:

```powershell
Install-WindowsFeature -Name Hyper-V -IncludeManagementTools -Restart
```

(On Windows 11/10 Pro instead of Server, use `Enable-WindowsOptionalFeature -Online -FeatureName Microsoft-Hyper-V -All` instead.) The host reboots to finish enabling it.

Skip this section if Hyper-V is already enabled (as it is on your box).

---

## 2. Create an External virtual switch bound to the real LAN NIC

This is what lets the VM sit directly on `192.168.1.0/24` instead of behind Hyper-V's NAT.

```powershell
# Identify the physical NIC connected to the USW-16-PoE / OPNsense LAN
Get-NetAdapter

# Create the External switch (replace "Ethernet" with the real adapter name)
New-VMSwitch -Name "LAN-Switch" -NetAdapterName "Ethernet" -AllowManagementOS $true
```

`-AllowManagementOS $true` is important on a single-NIC server - it keeps the host itself able to use that same adapter after the switch takes it over. Skip it only if this box has a second, dedicated NIC for its own management access.

---

## 3. Create the VM

```powershell
New-VM -Name "Zonclave" -Generation 2 -MemoryStartupBytes 4GB -NewVHDPath "D:\HyperV\Zonclave\Zonclave.vhdx" -NewVHDSizeBytes 60GB -SwitchName "LAN-Switch"

Set-VMProcessor -VMName "Zonclave" -Count 2
Set-VMMemory -VMName "Zonclave" -DynamicMemoryEnabled $true -MinimumBytes 2GB -MaximumBytes 6GB

# Attach the Ubuntu 22.04.5 ISO
Set-VMDvdDrive -VMName "Zonclave" -Path "D:\ISOs\ubuntu-22.04.5-live-server-amd64.iso"
# or the desktop ISO if you want the GUI variant, e.g. ubuntu-22.04.5-desktop-amd64.iso

# Generation 2 VMs need Secure Boot switched to the Linux-compatible template
Set-VMFirmware -VMName "Zonclave" -SecureBootTemplate "MicrosoftUEFICertificateAuthority"

# Boot from DVD first for install
Set-VMFirmware -VMName "Zonclave" -FirstBootDevice (Get-VMDvdDrive -VMName "Zonclave")

Start-VM -Name "Zonclave"
```

Connect via Hyper-V Manager's VM console to run through the Ubuntu installer (or `vmconnect` from PowerShell). 2 vCPU / 4-6GB RAM / 60GB disk is comfortable headroom for PostgreSQL + FreeRADIUS + nginx + the panel; more if you keep the desktop GUI running.

If your VM already exists (as yours does, with Ubuntu GUI already installed), skip straight to Section 4.

---

## 4. Run through the Ubuntu installer

Standard install: language, keyboard, disk (use the whole virtual disk), a local user account, and **install OpenSSH server when prompted** (saves a step later - if you missed it, Section 6 covers installing it after the fact).

Reboot when it finishes, remove the ISO:

```powershell
Set-VMDvdDrive -VMName "Zonclave" -Path $null
```

---

## 5. First boot: confirm LAN connectivity

Inside the VM:

```bash
ip a
ping -c 3 192.168.1.1   # OPNsense gateway - confirms the External switch is working
```

You should see an address in `192.168.1.0/24` (from the existing DHCP pool) and successful pings. If not, double check the VM's network adapter is attached to `LAN-Switch` (Hyper-V Manager → VM → Settings → Network Adapter).

---

## 6. Base OS setup

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y openssh-server git
sudo systemctl enable --now ssh
```

From here on, SSH in from your own machine instead of using the Hyper-V console window:

```bash
ssh <your-user>@<current-dhcp-ip>
```

Since this VM only needs to run as a server long-term, you can stop it from loading a desktop session on every boot (console access still works if you ever need the GUI):

```bash
sudo systemctl set-default multi-user.target
```

---

## 7. Set the static IP (192.168.1.250)

Find your interface name first:

```bash
ip a   # note the interface name, e.g. eth0 or enp0s3
```

```bash
sudo nano /etc/netplan/01-netcfg.yaml
```

```yaml
network:
  version: 2
  ethernets:
    eth0:        # replace with your actual interface name
      dhcp4: no
      addresses: [192.168.1.250/24]
      routes:
        - to: default
          via: 192.168.1.1
      nameservers:
        addresses: [192.168.1.1]
```

```bash
sudo netplan apply
ping -c 3 192.168.1.1   # confirm it's still reachable after leaving DHCP
```

Reconnect your SSH session using the new address from here on: `ssh <user>@192.168.1.250`.

**Recommended:** also add a static DHCP mapping for this same address in OPNsense (Services → DHCPv4 → [LAN] → static mappings, keyed to the VM's MAC address), so nothing can ever be handed `.250` by mistake even though it's outside the current pool.

---

## 8. Install PHP 8.3

Ubuntu 22.04's default repos only ship PHP 8.1, and Laravel 12 / Filament 5 need 8.2+. Add the `ondrej/php` PPA:

```bash
sudo apt install -y software-properties-common
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update
sudo apt install -y php8.3-fpm php8.3-cli php8.3-pgsql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath php8.3-gd php8.3-intl
```

**Confirm the CLI actually points at 8.3** - Ubuntu lets multiple PHP versions coexist via `update-alternatives`, and installing 8.3 alongside an existing 8.1 doesn't always switch the default automatically:

```bash
php -v
```

If that still shows 8.1.x:

```bash
sudo update-alternatives --set php /usr/bin/php8.3
php -v    # should now print PHP 8.3.x
```

This exact mismatch (packages installed, but `php` still resolving to 8.1) is the most common failure point in this whole setup - see the Troubleshooting section at the bottom if `composer install` still complains about PHP 8.1 after this.

---

## 9. Get the Zonclave project onto the VM

From your Windows machine, now that SSH is up:

```bash
scp -r "C:\Users\ZILL E\Videos\Dev\mikrotik\Zonclave\Zonclave" user@192.168.1.250:~/Zonclave
```

(Or `git clone` directly on the VM if it has access to the repo - `install-ubuntu22.04.sh` is tracked, so a fresh clone already includes it.)

---

## 10. Install the rest of the stack

```bash
cd ~/Zonclave
sudo bash installer/install-ubuntu22.04.sh
```

You'll be prompted for:

- **UniFi controller/AP subnet** - `192.168.1.0/24`
- **Panel admin email**

Everything else (DB credentials, RADIUS shared secret, admin password, seed PPSKs) is generated automatically and printed once at the end, plus saved to `/etc/ppsk-installer/install-summary.txt` (root-only). **Save that output somewhere safe.**

If composer fails partway through with PHP version errors, that means Section 8 wasn't fully applied before running the installer - jump to Troubleshooting below, fix it, then just re-run `sudo bash installer/install-ubuntu22.04.sh` again (it's idempotent).

---

## 11. Verify

From the VM:

```bash
sudo systemctl status postgresql freeradius nginx php8.3-fpm
curl -I http://127.0.0.1/
```

From your Windows machine's browser:

```text
http://192.168.1.250
```

should load the Zonclave login page. Log in with the admin email/password from the install summary.

---

## 12. Remaining manual steps (network side)

- **OPNsense**: add the static DHCP mapping for `192.168.1.250` if you haven't already (Section 7).
- **UniFi**: point the SSID's RADIUS profile at `192.168.1.250`, using the RADIUS shared secret from the install summary. See `docs/opnsense-configuration.md` and `docs/runbook/phase1-opnsense-unifi.md` for the rest of the VLAN/tunnel/firewall build-out - that part is unaffected by any of this (still the flat-LAN management decision from CLAUDE.md Section 3.4, PPSK VLANs 300-304 unchanged).

---

## Troubleshooting

**`composer install` fails with `php version (8.1.2) does not satisfy that requirement` even after installing php8.3-\* packages:**
The `php` command is still aliased to 8.1. Fix:

```bash
php8.3 -v   # confirm 8.3 is actually installed
sudo update-alternatives --set php /usr/bin/php8.3
php -v      # should now show 8.3.x
cd ~/Zonclave/panel && composer install
```

**`add-apt-repository ppa:ondrej/php` seems to silently do nothing:**
Check internet reachability from the VM first (`ping 8.8.8.8`, `curl -I https://ppa.launchpadcontent.net`) - this step needs to reach Launchpad. Re-run `sudo apt update` afterward and confirm with `apt-cache policy php8.3-cli` that the package is now visible before installing.

**VM has no network / can't reach 192.168.1.1 after attaching to LAN-Switch:**
Confirm in Hyper-V Manager that the VM's network adapter is actually connected to `LAN-Switch`, not `Default Switch`. Confirm the physical NIC picked in Section 2 is the one actually cabled to the USW-16-PoE.

**Installer log for anything not covered above:**

```bash
sudo tail -100 /var/log/ppsk-install.log
```
