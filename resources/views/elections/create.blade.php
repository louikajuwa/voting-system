<x-app-layout>
    <div class="page-h" style="justify-content:center;text-align:center"><div><h1>New election</h1><p>Set up a ballot, then add candidates.</p></div></div>

    <div class="card pad" style="max-width:640px;margin:0 auto">
        <form method="POST" action="{{ route('admin.elections.store') }}">
            @csrf
            @include('elections._form')
            <div class="form-actions">
                <button type="submit" class="btn primary">Create election</button>
                <a href="{{ route('dashboard') }}" class="btn">Cancel</a>
            </div>
        </form>
    </div>
</x-app-layout>
