<h2>Delete Account</h2>
<p class="text-muted" style="margin-bottom:1rem;">Once deleted, all your data will be permanently removed.</p>

<form method="POST" action="{{ route('profile.destroy') }}"
      onsubmit="return confirm('Are you sure you want to delete your account? This cannot be undone.')">
    @csrf
    @method('DELETE')
    <button type="submit" class="btn btn-danger">Delete Account</button>
</form>
