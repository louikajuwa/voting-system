<x-app-layout>
    <div class="page-h"><div><h1>New candidate</h1><p>Add a candidate to {{ $election->title }}. The photo appears on the voter ballot.</p></div></div>

    <div class="card pad">
        <form method="POST" action="{{ route('admin.elections.candidates.store', $election) }}" enctype="multipart/form-data">
            @csrf
            @include('candidates._form')
            <div class="form-actions">
                <button type="submit" class="btn primary">Save candidate</button>
                <a href="{{ route('admin.elections.show', $election) }}" class="btn">Cancel</a>
            </div>
        </form>
    </div>
</x-app-layout>
