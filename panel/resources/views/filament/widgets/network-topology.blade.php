<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Network Topology</x-slot>
        <x-slot name="description">
            The provisioned architecture, from the registry - not live device or tunnel status. See the OPNsense/UniFi runbook for real-time health.
        </x-slot>

        <div class="zc-topology">
            <div class="zc-topo-node zc-topo-node--root">Internet</div>
            <div class="zc-topo-line zc-topo-line--vertical"></div>
            <div class="zc-topo-node zc-topo-node--root">OPNsense</div>
            <div class="zc-topo-branches">
                @foreach ($this->getVlanNodes() as $node)
                    <div class="zc-topo-branch">
                        <div class="zc-topo-line zc-topo-line--vertical zc-topo-line--short"></div>
                        <a href="{{ $node['url'] }}" class="zc-topo-node zc-topo-node--vlan">
                            <span class="zc-topo-vlan-id">VLAN {{ $node['vlan_id'] }}</span>
                            <span class="zc-topo-vlan-detail">{{ $node['subnet'] }}</span>
                            <span class="zc-topo-vlan-detail">{{ $node['wireguard_interface'] }}</span>
                            <span class="zc-topo-vlan-counts">
                                @if ($node['active'] > 0)
                                    <span class="zc-topo-badge zc-topo-badge--active">{{ $node['active'] }} active</span>
                                @endif
                                @if ($node['disabled'] > 0)
                                    <span class="zc-topo-badge zc-topo-badge--disabled">{{ $node['disabled'] }} disabled</span>
                                @endif
                                @if ($node['active'] === 0 && $node['disabled'] === 0)
                                    <span class="zc-topo-badge zc-topo-badge--empty">unprovisioned</span>
                                @endif
                            </span>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
