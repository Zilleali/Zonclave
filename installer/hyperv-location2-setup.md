# Hyper-V + Ubuntu 22.04.5 LTS: Zonclave Setup - Location 2 (SancoverPC-5)

Complete, start-to-finish steps for standing up Zonclave inside a Hyper-V VM on `SancoverPC-5`, ending with a working panel + FreeRADIUS reachable at `192.168.1.4`.

This is the Location 2-specific runbook, derived from the generic [`hyperv-ubuntu22.04-setup.md`](hyperv-ubuntu22.04-setup.md) - same steps, but with this location's actual confirmed values filled in rather than the generic `.250` placeholder. See CLAUDE.md Section 27 for the decision record and live status tracking; this file is the actionable steps, that section is the source of truth if the two ever disagree.

**Why a second, independent node at all:** CLAUDE.md Section 3.3 records the architecture reversal (2026-07-28) - each location runs its own fully independent Zonclave install (own FreeRADIUS, own panel, own PostgreSQL, own PPSK registry), not one shared central server. This build has no dependency on the Kelder node (`SancoverPC-4`) staying up, and vice versa.

If your VM already exists with Ubuntu installed, skip to whichever section matches where you actually are - each section is self-contained.

---

## 0. What you end up with

- A Hyper-V VM running Ubuntu 22.04.5 LTS on `SancoverPC-5`, bridged directly onto Location 2's real LAN (`192.168.1.0/24`) - not Hyper-V's NAT'd default switch.
- Host static IP: **`192.168.1.5/24`** (confirmed 2026-07-28).
- VM static IP: **`192.168.1.4/24`** (confirmed 2026-07-28) - the VM's LAN-facing adapter, bridged through the External vSwitch (Section 2).
- PHP 8.3 (via the `ondrej/php` PPA, since 22.04's default repos only ship 8.1).
- PostgreSQL, FreeRADIUS, nginx, and the Zonclave panel, all provisioned by `install-ubuntu22.04.sh` - a fully independent database and PPSK registry from Kelder's, per the Section 3.3 architecture decision.

**Before you start - the one thing not yet confirmed:** `192.168.1.4` and `192.168.1.5` are low in the range and likely below where Location 2's DHCP pool actually starts, but this needs checking against Location 2's own OPNsense DHCP scope (Services > DHCPv4 > [LAN]), not assumed safe. Kelder's own static IPs (`192.168.1.174`/`.175`) turned out to sit *inside* its pool despite looking deliberately chosen - only caught because someone checked. If either address falls inside Location 2's pool, add a static DHCP mapping keyed to MAC address for it (same fix Kelder needed, CLAUDE.md Section 3.4) before relying on it staying put.

---

## 1. Enable Hyper-V on SancoverPC-5

Elevated PowerShell:

```powershell
Install-WindowsFeature -Name Hyper-V -IncludeManagementTools -Restart
```

(On Windows 11/10 Pro instead of Server, use `Enable-WindowsOptionalFeature -Online -FeatureName Microsoft-Hyper-V -All` instead.) The host reboots to finish enabling it.

Skip this section if Hyper-V is already enabled on this box.

---

## 2. Create an External virtual switch bound to the real LAN NIC

This is what lets the VM sit directly on `192.168.1.0/24` instead of behind Hyper-V's NAT - the same "External Interface" the VM's `192.168.1.4` address is assigned on.

```powershell
# Identify the physical NIC connected to Location 2's switch/OPNsense LAN
Get-NetAdapter

# Create the External switch (replace "Ethernet" with the real adapter name)
New-VMSwitch -Name "LAN-Switch" -NetAdapterName "Ethernet" -AllowManagementOS $true
```

`-AllowManagementOS $true` is important on a single-NIC server - it keeps the host itself able to use that same adapter after the switch takes it over. Skip it only if `SancoverPC-5` has a second, dedicated NIC for its own management access.

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

2 vCPU / 4-6GB RAM / 60GB disk matches what Kelder's node runs comfortably (PostgreSQL + FreeRADIUS + nginx + the panel). Adjust the drive letters/paths above (`D:\HyperV\...`, `D:\ISOs\...`) to match whatever storage `SancoverPC-5` actually has - these are the same conventions the generic guide uses, not a hard requirement.

Connect via Hyper-V Manager's VM console to run through the Ubuntu installer (or `vmconnect` from PowerShell).

If your VM already exists, skip straight to Section 4.

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

You should see an address in `192.168.1.0/24` (from Location 2's existing DHCP pool, before the static IP is set in Section 7) and successful pings. If not, double check the VM's network adapter is attached to `LAN-Switch` (Hyper-V Manager -> VM -> Settings -> Network Adapter), and that the physical NIC picked in Section 2 is the one actually cabled to Location 2's switch/OPNsense LAN port.

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

## 7. Set the static IP (192.168.1.4)

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
      addresses: [192.168.1.4/24]
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

Reconnect your SSH session using the new address from here on: `ssh <user>@192.168.1.4`.

**Do this before going further** (see the "Before you start" note in Section 0): confirm `192.168.1.4` is actually outside Location 2's DHCP pool, and add a static DHCP mapping for it in OPNsense (Services > DHCPv4 > [LAN] > static mappings, keyed to the VM's MAC address) regardless, so nothing can ever hand it out by mistake.

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

This exact mismatch (packages installed, but `php` still resolving to 8.1) was the most common failure point when Kelder's node was built - see the Troubleshooting section at the bottom if `composer install` still complains about PHP 8.1 after this.

---

## 9. Get the Zonclave project onto the VM

From your Windows machine, now that SSH is up:

```bash
scp -r "C:\Users\ZILL E\Videos\Dev\mikrotik\Zonclave\Zonclave" user@192.168.1.4:~/Zonclave
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

Everything else (DB credentials, RADIUS shared secret, admin password, seed PPSKs) is generated automatically and printed once at the end, plus saved to `/etc/ppsk-installer/install-summary.txt` (root-only). **Save that output somewhere safe** - it's a separate, independent set of secrets from Kelder's own install summary (Section 3.3's architecture decision means these two nodes never share credentials).

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
http://192.168.1.4
```

should load the Zonclave login page. Log in with the admin email/password from the install summary.

---

## 12. Remaining manual steps

- **Windows host hardening on `SancoverPC-5`** (CLAUDE.md Section 3.3, same three settings Kelder needed): disable automatic reboot on update, set the VM's `AutomaticStartAction` to `Start`, set power plan to High Performance / sleep-never. Verify with the same PowerShell checks documented in CLAUDE.md Section 3.3.
- **OPNsense**: add the static DHCP mapping for `192.168.1.4` if you haven't already (Section 7), and confirm `192.168.1.5` (the host) doesn't collide with the pool either.
- **UniFi**: point Location 2's SSID RADIUS profile at `192.168.1.4`, using the RADIUS shared secret from *this* install summary (not Kelder's - these are independent nodes with independent secrets).
- **VLANs, WireGuard tunnels, firewall rules**: see `docs/opnsense-configuration.md` and `docs/runbook/phase1-opnsense-unifi.md` for the full build-out. Decide whether Location 2 reuses VLANs 300-304 (same IDs, different physical site - the VLAN block per CLAUDE.md Section 5 is a design pattern replicated per router, not a single global allocation) or continues the block, and confirm Location 2's own WireGuard peer configs are in hand first (CLAUDE.md Section 20).
- Once done, update CLAUDE.md Section 27 with anything that changed from the plan (actual hardware specs, confirmed host OS, any DHCP pool findings) - same way Section 26 was kept current for Kelder as its build actually happened.

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
Confirm in Hyper-V Manager that the VM's network adapter is actually connected to `LAN-Switch`, not `Default Switch`. Confirm the physical NIC picked in Section 2 is the one actually cabled to Location 2's switch/OPNsense LAN port.

**Installer log for anything not covered above:**

```bash
sudo tail -100 /var/log/ppsk-install.log
```
