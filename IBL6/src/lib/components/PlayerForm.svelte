<script>
    import { createEventDispatcher } from 'svelte';

    const dispatch = createEventDispatcher();

    let formData = {
        name: '',
        position: '',
        minutes: ''
    };

    function handleFormSubmit(event) {
        event.preventDefault();
        dispatch('addPlayer', { ...formData });
        resetForm();
    }

    function resetForm() {
        formData = {
            name: '',
            position: '',
            minutes: ''
        };
    }
</script>


<!-- Form section -->
<div class="card bg-base-100 shadow-xl mt-8">
    <div class="card-header p-6">
        <h2 class="card-title">Add New Player</h2>
    </div>
    <form class="card-body" onsubmit={handleFormSubmit}>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="form-control">
                <label class="label" for="player-name">
                    <span class="label-text">Player Name</span>
                </label>
                <input 
                    id="player-name"
                    type="text" 
                    class="input input-bordered" 
                    bind:value={formData.name}
                    required
                />
            </div>
            
            <div class="form-control">
                <label class="label" for="player-position">
                    <span class="label-text">Position</span>
                </label>
                <select 
                    id="player-position"
                    class="select select-bordered"
                    bind:value={formData.position}
                    required
                >
                    <option value="" disabled>Select position</option>
                    <option value="PG">PG</option>
                    <option value="SG">SG</option>
                    <option value="SF">SF</option>
                    <option value="PF">PF</option>
                    <option value="C">C</option>
                </select>
            </div>
            
            <div class="form-control">
                <label class="label" for="player-minutes">
                    <span class="label-text">Minutes</span>
                </label>
                <input 
                    id="player-minutes"
                    type="number" 
                    class="input input-bordered" 
                    bind:value={formData.minutes}
                    min="0" 
                    max="48" 
                />
            </div>
        </div>
        
        <div class="card-actions justify-end mt-6">
            <button type="button" class="btn btn-outline" onclick={resetForm}>
                Cancel
            </button>
            <button type="submit" class="btn btn-primary">
                Add Player
            </button>
        </div>
    </form>
</div>