<x-app-layout>
    <div class="page-h" style="justify-content:center;text-align:center"><div><h1>Edit election</h1><p>{{ $election->title }}</p></div></div>

    <div class="card pad" style="max-width:640px;margin:0 auto">
        <form method="POST" action="{{ route('admin.elections.update', $election) }}">
            @csrf
            @method('PUT')
            @include('elections._form')
            <div class="form-actions">
                <button type="submit" class="btn primary">Save changes</button>
                <a href="{{ route('admin.elections.show', $election) }}" class="btn">Cancel</a>
            </div>
        </form>
    </div>
</x-app-layout>
