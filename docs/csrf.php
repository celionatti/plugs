<!-- Regular form -->
<form method="POST" action="/submit">
    <?= Csrf::field() ?>
    <input type="text" name="data">
    <button type="submit">Submit</button>
</form>

<!-- For AJAX (in layout head) -->
<?= Csrf::metaTag() ?>

<script>
// Fetch API
fetch('/api/data', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    },
    body: JSON.stringify({data: 'value'})
});

// jQuery
$.ajaxSetup({
    headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
});
</script>