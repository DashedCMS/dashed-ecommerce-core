<div>
    <script>
        toastr.options.progressBar = true;
        toastr.options.showMethod = 'slideDown';
        toastr.options.positionClass = 'toast-bottom-right';
    </script>

    @if(session('success'))
        <script>
            toastr.success('{{ session('success') }}');
        </script>
    @endif
    @if(session('error'))
        <script>
            toastr.error('{{ session('error') }}');
        </script>
    @endif
    @if ($errors->any())
        @foreach ($errors->all() as $error)
            <script>
                toastr.error('{{ $error }}');
            </script>
        @endforeach
    @endif

    <script>
        window.addEventListener('alert', event => {
            if (event.detail.type === 'success') {
                toastr.success(event.detail.message);
            } else {
                toastr.error(event.detail.message);
            }
        });
    </script>
</div>
