@if (session('status'))
    <div class="mb-4 rounded-2xl border border-vault-success/30 bg-vault-success/10 px-4 py-3 text-sm font-semibold text-vault-text" role="status">
        {{ session('status') }}
    </div>
@endif

@if ($errors->has('api'))
    <div class="mb-4 rounded-2xl border border-vault-danger/30 bg-vault-danger/10 px-4 py-3 text-sm text-vault-danger" role="alert">
        <ul class="space-y-1">
            @foreach ($errors->get('api') as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
