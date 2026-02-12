@extends('layouts.admin')

@section('title', 'AI Post Suggestions')

@section('content')
<div class="ai-dashboard mt-4">
    <div class="header mb-4">
        <h2><i class="bi bi-stars"></i> Smart Topic Suggestions</h2>
        <p class="text-muted">AI-powered trending ideas for your content.</p>
    </div>

    <div class="row">
        @foreach($topics as $topic)
        <div class="col-md-6 mb-3">
            <div class="card shadow-sm border-0 border-start border-primary border-4">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">{{ $topic }}</h5>
                    </div>
                    <form action="/admin/articles/generate" method="POST">
                        @csrf
                        <input type="hidden" name="topic" value="{{ $topic }}">
                        <button class="btn btn-sm btn-outline-primary">Draft Now</button>
                    </form>
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endsection