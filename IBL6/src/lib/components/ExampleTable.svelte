<script>
  // Sample data for a 10x10 table
  let data = [];
  
  // Generate sample data
  for (let i = 0; i < 10; i++) {
    let row = [];
    for (let j = 0; j < 10; j++) {
      row.push(`Cell ${i + 1}-${j + 1}`);
    }
    data.push(row);
  }
  
  // Headers for the table
  let headers = ['Name', 'ID', 'Status', 'Date', 'Amount', 'Category', 'Location', 'Notes', 'Priority', 'Actions'];
</script>

<div class="w-full max-w-6xl mx-auto">
  <h2 class="text-2xl font-bold mb-4">Frozen Columns Table</h2>
  
  <!-- Table container with horizontal scroll -->
  <div class="overflow-x-auto border border-base-300 rounded-lg shadow-lg">
    <table class="table table-zebra table-pin-rows min-w-full">
      <!-- Header -->
      <thead class="bg-base-200">
        <tr>
          {#each headers as header, index}
            <th 
              class="
                {index < 2 ? 'sticky bg-base-200 z-20' : ''}
                {index === 0 ? 'left-0 shadow-[2px_0_5px_rgba(0,0,0,0.1)]' : ''}
                {index === 1 ? 'left-24 shadow-[2px_0_5px_rgba(0,0,0,0.1)]' : ''}
                min-w-24 px-4 py-3 font-semibold
              "
            >
              {header}
            </th>
          {/each}
        </tr>
      </thead>
      
      <!-- Body -->
      <tbody>
        {#each data as row, rowIndex}
          <tr class="hover:bg-base-100 transition-colors">
            {#each row as cell, colIndex}
              <td 
                class="
                  {colIndex < 2 ? 'sticky bg-inherit z-10' : ''}
                  {colIndex === 0 ? 'left-0 shadow-[2px_0_5px_rgba(0,0,0,0.08)]' : ''}
                  {colIndex === 1 ? 'left-24 shadow-[2px_0_5px_rgba(0,0,0,0.08)]' : ''}
                  min-w-24 px-4 py-3 whitespace-nowrap
                "
              >
                {#if colIndex === 0}
                  <!-- Special styling for first column (Name) -->
                  <div class="font-medium text-base-content">
                    {cell}
                  </div>
                {:else if colIndex === 1}
                  <!-- Special styling for second column (ID) -->
                  <div class="badge badge-outline badge-sm">
                    {cell}
                  </div>
                {:else if colIndex === 2}
                  <!-- Status column with badge -->
                  <div class="badge badge-success badge-sm">
                    Active
                  </div>
                {:else if colIndex === 8}
                  <!-- Priority column with different badge colors -->
                  <div class="badge {rowIndex % 3 === 0 ? 'badge-error' : rowIndex % 3 === 1 ? 'badge-warning' : 'badge-info'} badge-sm">
                    {rowIndex % 3 === 0 ? 'High' : rowIndex % 3 === 1 ? 'Medium' : 'Low'}
                  </div>
                {:else if colIndex === 9}
                  <!-- Actions column with buttons -->
                  <div class="flex gap-1">
                    <button class="btn btn-ghost btn-xs">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                      </svg>
                    </button>
                    <button class="btn btn-ghost btn-xs text-error">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                      </svg>
                    </button>
                  </div>
                {:else}
                  {cell}
                {/if}
              </td>
            {/each}
          </tr>
        {/each}
      </tbody>
    </table>
  </div>
  
  <!-- Info card showing the features -->
  <div class="card bg-base-100 shadow-md mt-6">
    <div class="card-body">
      <h3 class="card-title text-lg">Features Demonstrated:</h3>
      <ul class="list-disc list-inside space-y-1 text-sm">
        <li><span class="font-medium">Frozen Columns:</span> First two columns stay visible while scrolling horizontally</li>
        <li><span class="font-medium">DaisyUI Components:</span> Uses table, badges, buttons, and card components</li>
        <li><span class="font-medium">Responsive Design:</span> Horizontal scroll on smaller screens</li>
        <li><span class="font-medium">Interactive Elements:</span> Hover effects and action buttons</li>
        <li><span class="font-medium">Tailwind Utilities:</span> Clean, utility-first CSS approach</li>
      </ul>
    </div>
  </div>
</div>