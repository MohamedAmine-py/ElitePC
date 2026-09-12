# PC Hardware Reference

This reference explains general hardware principles. It does not describe ElitePC inventory or guarantee performance for a particular product. Use current catalog information for exact product specifications, prices, and stock. Workload advice is a starting point; measured performance and the requirements of the customer's actual software take priority.

## CPUs

A CPU runs game logic, operating-system tasks, and application code. A core is a processing unit; a thread is a stream of work. Some cores can process multiple hardware threads, but two threads on one core do not provide the same resources as two separate cores. Software must distribute work effectively to benefit from additional cores.

Clock speed describes cycles per second, not a universal performance score. Instructions per clock, or IPC, describes how much instruction work an architecture completes per cycle for a given workload. Different architectures, cache designs, and programs behave differently. Higher GHz does not automatically beat a lower-clocked CPU from another design. Boost clocks also depend on power, temperature, and the number of active cores. See [Intel's CPU selection guide](https://www.intel.com/content/www/us/en/gaming/resources/gaming-cpu.html).

Games often depend on the speed of a few demanding threads as well as sufficient resources for background work. Rendering, compiling large projects, and some encoding tasks may benefit more from additional cores, provided the application scales across them.

A CPU bottleneck occurs when the processor cannot prepare work quickly enough for the target frame rate or application throughput. Total CPU utilization can look modest while one critical thread is saturated. Reducing resolution without improving performance can be a clue, but frame caps, cooling limits, memory pressure, and software problems must also be considered.

Choose a CPU around the games or applications, desired responsiveness, GPU pairing, cooling budget, and total platform cost. Buying the largest core count is not automatically the best use of a gaming budget.

## GPUs

A GPU handles graphics rendering and supported parallel-compute tasks. Performance depends on the exact processor design, workload, power limits, memory system, and software support. Clock speed, core counts, or model numbering alone are unreliable comparisons across architectures.

VRAM holds graphics resources such as textures, frame buffers, and scene data. Running short can cause uneven frame delivery, reduced texture quality, or failed workloads. Having more VRAM than a task needs does not automatically make a GPU faster. Video editing and GPU rendering can have different memory needs from gaming.

Higher rendering resolution usually increases GPU work. Texture quality can increase VRAM demand; shadows, lighting, geometry, and other settings affect different parts of the system. “Ultra” is not a universal requirement for a good image.

Ray tracing simulates aspects of light transport and can improve reflections, shadows, or lighting when a game supports it. It can also add substantial rendering cost. Upscaling reconstructs a higher-resolution output from lower-resolution input; results depend on the algorithm, game integration, motion, and quality setting. Frame generation creates additional displayed frames and is distinct from increasing the rate at which the game simulates new input. Assess responsiveness and image artifacts as well as the displayed frame rate. [NVIDIA's DLSS overview](https://www.nvidia.com/en-us/geforce/technologies/dlss/) illustrates these separate techniques; feature support must be checked for the actual game and GPU.

A GPU bottleneck is likely when rendering load limits performance and reducing resolution or demanding effects improves it. Choose a GPU for the target monitor, games, quality settings, and required application features, then verify power and physical fit separately.

## RAM

RAM holds active programs and working data. Capacity prevents workloads from spilling excessively into slower storage. More capacity helps when memory is constrained; unused extra capacity does not inherently improve performance.

As general planning guidance, 16 GB can suit lighter gaming and everyday use when the actual software fits. 32 GB gives more room for demanding games, browser tabs, streaming tools, and creative applications running together. Higher capacities make sense for large editing projects, complex scenes, virtual machines, datasets, or heavy multitasking. Check software requirements and observed usage before buying a larger kit.

Memory transfer rate, commonly expressed in MT/s, affects bandwidth. Latency describes delays in accessing data. A lower CAS number alone does not prove lower real latency when transfer rates differ. Capacity, bandwidth, latency, and CPU memory behavior interact; memory specifications are not a guaranteed frame-rate uplift.

DDR4 and DDR5 are different memory generations and are not interchangeable in the same DIMM slot. DDR5 offers different signaling and higher bandwidth potential, but the platform must support it. [Kingston's DDR generation guide](https://www.kingston.com/unitedkingdom/en/blog/pc-performance/ddr-memory-generation-differences) explains the platform distinction.

On a typical dual-channel desktop platform, a suitable pair of modules in the recommended slots can provide more aggregate bandwidth than one module. DDR5's internal subchannels do not eliminate the need to follow the motherboard's population guidance. Two sticks are not automatically configured optimally just because both fit.

## Storage

An HDD stores data on spinning disks. It can suit bulk archives where access speed is secondary. A SATA SSD has no moving parts and usually makes booting, application launches, and random file access much more responsive than an HDD. An NVMe SSD communicates over PCIe and can offer higher throughput and more efficient parallel access.

Capacity should cover the operating system, installed applications, games, active projects, updates, and working space. Keep room for temporary files rather than planning to operate continuously at full capacity. An additional drive is not a backup if it holds the only copy of important files.

Moving a game from an HDD to an SSD can reduce loading delays and help with workloads that stream assets. Moving between SSD tiers does not guarantee higher gameplay frame rates. Large file transfers, scratch disks, and media workflows can benefit more clearly from additional storage throughput.

Advertised peak sequential speed is only one measure. Small-file access, sustained writes, drive fullness, cooling, and the source or destination drive can affect results.

M.2 describes a physical format, not a promise of NVMe support or speed. M.2 drives can use different interfaces. [Kingston's SSD form-factor guide](https://www.kingston.com/en/blog/pc-performance/ssd-form-factors) explains this distinction; confirm the motherboard's exact slot support before purchase.

## Motherboards

A motherboard connects the CPU, memory, storage, expansion cards, and external devices. Its socket is a physical and electrical CPU interface. Its chipset helps determine platform features and connectivity, but a chipset name alone does not confirm support for a particular CPU.

Evaluate the actual board's memory generation and capacity limits, power delivery, expansion layout, storage connections, and external ports. Look for the USB types, networking, wireless connectivity, audio connections, and display outputs needed for the intended setup. A motherboard display connector does not guarantee usable integrated graphics with every CPU.

Check how many M.2 slots are available, which interfaces and device lengths they support, and whether using one disables or reduces another connection. A long PCIe slot is not necessarily wired with the maximum number of lanes.

Higher motherboard tiers often add connectivity, stronger power delivery, or convenience features. They do not automatically make a supported CPU faster at equivalent operating settings. Choose useful features and adequate sustained power delivery before decorative extras. Leave exact component matching to verified specifications and support lists.

## Power Supplies

A PSU supplies electrical power to the components. Its wattage rating describes output capacity; it does not mean the PC constantly consumes that amount. GPU recommendations commonly refer to an entire system, while CPU thermal or power labels are not a complete system power budget.

Choose capacity using the CPU and GPU's documented requirements, other components, and sustained workload. Allow suitable headroom for short power excursions and realistic upgrades. There is no single headroom percentage that proves every configuration safe.

Efficiency describes the relationship between power drawn from the wall and power delivered to the PC. It does not mean a rated PSU can deliver only its efficiency percentage of its stated output. Efficiency also varies with load. See [Corsair's efficiency explanation](https://help.corsair.com/hc/en-us/articles/14641912717453-PSU-Efficiency-Ratings-Explained).

Quality includes voltage regulation, protective functions, behavior under changing loads, thermal design, and component quality. An efficiency badge alone does not establish overall quality, as explained in [Corsair's PSU quality discussion](https://www.corsair.com/us/en/explorer/diy-builder/power-supply-units/how-to-avoid-power-supply-pitfalls/). Evaluate the exact unit and required connectors, not just the brand or a large wattage label.

## Cooling

An air cooler moves CPU heat into a heatsink and removes it with airflow. An all-in-one liquid cooler transfers heat to a radiator that still needs fans and case airflow. Neither type is automatically quieter or faster: cooler size, design, installation, power load, and fan settings matter. [Intel's air-versus-liquid guide](https://www.intel.com/content/www/us/en/gaming/resources/cpu-cooler-liquid-cooling-vs-air-cooling.html) describes both approaches.

CPU temperature must be interpreted against the exact processor's limits, workload, ambient temperature, and power settings. There is no universal temperature threshold that proves every CPU healthy or overheating. Check whether sustained work causes throttling, excessive noise, or instability.

Good case airflow supplies cooler air to heat-producing components and gives warmed air a clear exit. Blocked intake panels, dust, cable obstruction, and poorly arranged fans can undermine a capable cooler.

Stronger cooling matters most when sustained power exceeds the current cooler's practical capacity or when quieter operation is a priority. It will not fix a workload limited by an unrelated component. Check mounting hardware, RAM clearance, cooler height, and radiator fit before choosing a replacement.
