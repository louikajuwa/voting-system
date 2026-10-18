<x-app-layout>
    <div class="page-h"><div><h1>Edit candidate</h1><p>{{ $candidate->name }} · {{ $election->title }}</p></div></div>

    <div class="card pad">
        <form method="POST" action="{{ route('admin.elections.candidates.update', [$election, $candidate]) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @include('candidates._form')
            <div class="form-actions">
                <button type="submit" class="btn primary">Save changes</button>
                <a href="{{ route('admin.elections.show', $election) }}" class="btn">Cancel</a>
            </div>
        </form>
    </div>
</x-app-layout>
