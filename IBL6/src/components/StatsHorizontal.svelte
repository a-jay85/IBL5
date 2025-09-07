<script lang="ts">
    export let headers: string[] = [];
</script>

<tr class="transition-colors">
    {#each headers as header, index}
        <th 
            class="
            {header === 'Name'? 'sticky z-20 left-0 bg-base-100' : ''}
            {index === 0 ? 'left-0 shadow-[2px_0_5px_rgba(0,0,0,0.1)]' : ''}
            {index > 1 ? 'left-24 shadow-[2px_0_5px_rgba(0,0,0,0.1)]' : ''}
            min-w-10 px-4 py-3 whitespace-nowrap font-semibold
            "
        >
            {#if index > 1}
                {header.toUpperCase()}
            {:else}
                {header}
            {/if}
        </th>
    {/each}
</tr>
