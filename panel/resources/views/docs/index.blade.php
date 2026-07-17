@extends('layouts.public')

@section('title', 'Documentation - Zonclave')
@section('description', 'Zonclave documentation: installation guide and command reference.')

@section('content')

    <section class="doc-article">
        <p class="eyebrow">Documentation</p>
        <h1>Zonclave docs</h1>
        <p class="doc-lede">Everything needed to set up, install, and run Zonclave.</p>

        <div class="link-grid">
            <a href="{{ url('/docs/installation-guide') }}" class="card card-link">
                <h2>Installation guide</h2>
                <p>The full start-to-finish manual: panel setup, production installation, and how to use it day to day.</p>
            </a>

            <a href="{{ url('/docs/commands-reference') }}" class="card card-link">
                <h2>Command reference</h2>
                <p>Every command used to develop, test, and install Zonclave - a cheat sheet, no explanation.</p>
            </a>

            <a href="{{ url('/docs/opnsense-configuration') }}" class="card card-link">
                <h2>OPNsense configuration guide</h2>
                <p>How the network side is built: VLANs, WireGuard tunnels, fail-closed firewall policy, DNS, and the UniFi integration.</p>
            </a>

            <a href="{{ url('/docs/site-configuration') }}" class="card card-link">
                <h2>Site configuration: Office SancoMedia Kelder</h2>
                <p>The real, live deployment - actual hardware, addressing, VLAN/tunnel build, and current status.</p>
            </a>

            <a href="{{ url('/docs/troubleshooting') }}" class="card card-link">
                <h2>Troubleshooting</h2>
                <p>Real issues hit during deployment, each with the actual symptom, root cause, and fix.</p>
            </a>
        </div>
    </section>

@endsection
