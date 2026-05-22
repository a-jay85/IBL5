<script lang="ts">
    import { page } from '$app/state';
</script>

<div data-testid="boxscore-not-found" class="flex flex-col items-center justify-center p-12 text-center">
    <div class="text-6xl mb-4">🏀</div>
    <h1 class="text-2xl font-bold mb-2">{page.status}</h1>
    <p class="text-base-content/60">{page.error?.message ?? 'Game not found'}</p>
</div>
