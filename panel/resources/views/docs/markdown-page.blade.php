@extends('layouts.public')

@section('title', $title.' - Zonclave')
@section('description', $description)

@section('content')

    <article class="doc-article">
        <a href="{{ url('/docs') }}" class="doc-back">&larr; Documentation</a>

        <h1>{{ $title }}</h1>
        <p class="doc-lede">{{ $description }}</p>

        <div class="doc-body doc-body--markdown">
            {!! $html !!}
        </div>

        <section class="card doc-cta" style="margin-top:3rem">
            <h2 style="font-size:1.125rem">{{ $ctaHeading }}</h2>
            <div class="hero-actions" style="margin-top:1rem">
                @if ($ctaUrl)
                    <a href="{{ $ctaUrl }}" class="btn btn-primary">{{ $ctaLabel }}</a>
                @else
                    <a href="mailto:zilleali1245@gmail.com?subject=Zonclave%20inquiry" class="btn btn-primary">{{ $ctaLabel }}</a>
                @endif
            </div>
        </section>
    </article>

@endsection
