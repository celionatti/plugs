@extends('layouts.admin')

@section('title', 'AI Content Audit')

@section('content')
<div class="audit-report py-4">
    <div class="alert alert-info border-0 shadow-sm d-flex align-items-center">
        <i class="bi bi-shield-check fs-2 me-3"></i>
        <div>
            <h4 class="mb-0">AI Quality Audit</h4>
            <p class="mb-0 opacity-75">Analysis of readability, tone, and factual consistency.</p>
        </div>
    </div>

    <div class="card border-0 shadow-sm mt-4">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-3">Audit Details</h5>
            <div class="content">
                {!! nl2br($report) !!}
            </div>
            <hr>
            <div class="actions text-end">
                <button class="btn btn-primary" onclick="window.print()">
                    <i class="bi bi-printer"></i> Export Report
                </button>
            </div>
        </div>
    </div>
</div>
@endsection