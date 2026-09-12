# PC Compatibility Reference

Compatibility is a relationship between exact components and their intended configuration. General standards explain what to check; they do not prove that two ElitePC products work together. Product names, appearance, and marketing descriptions are not substitutes for verified specifications.

## CPU and Motherboard Compatibility

Check the exact CPU model against the motherboard manufacturer's CPU support list for the exact board model and, where relevant, hardware revision.

A matching socket is necessary for a conventional socketed desktop CPU, but it is not sufficient. Chipset support, board design, firmware, and supported power operation can still differ. CPUs within a marketing family may not all share the same support requirements.

The support list may require a minimum BIOS version. If the board's installed version is unknown, compatibility may be conditional rather than ready to use. Do not assume that a BIOS update can be performed without an already supported CPU; that requires a documented feature on the exact board.

Identify missing facts explicitly: exact board model, CPU support entry, required BIOS, installed BIOS, and suitable cooling or power support.

## RAM Compatibility

Confirm the memory generation supported by both the CPU platform and the exact motherboard. DDR4 and DDR5 modules cannot be substituted in the same slot. A processor family supporting more than one generation does not mean every motherboard for it does.

Check module form factor, total capacity, capacity per module, slot count, and whether the board supports the memory type, such as unbuffered or registered modules. Desktop DIMMs and laptop SO-DIMMs are not interchangeable. ECC support also requires explicit platform confirmation.

Supported speed depends on the processor, board, firmware, and DIMM configuration. An advertised overclocking profile is not guaranteed operation for every setup. Higher module counts can reduce supported speed. Use the recommended slot population and a matched kit where practical; separately purchased kits with similar labels are not automatically validated together.

A qualified memory list documents tested configurations but is not necessarily an exhaustive list of all working memory. Absence from that list alone is insufficient to declare incompatibility. [Kingston's memory population guidance](https://www.kingston.com/unitedkingdom/en/memory/memory-population-rules) illustrates why configuration matters.

## GPU Compatibility

Check the GPU's slot interface, dimensions, power requirements, and display connections against the complete PC.

PCIe devices can generally negotiate a mutually supported generation and lane width, but this principle alone does not verify a whole installation. The motherboard slot's electrical lane allocation, firmware support, and the GPU's requirements still matter. A physically long slot may have fewer connected lanes than its length suggests.

Measure length, height, thickness, and occupied expansion slots for the exact card. Allow room for power cables, connector clearance, and the manufacturer's bend guidance. Front radiators, drive cages, and other cards can reduce usable space compared with an empty case's advertised clearance.

Verify a suitable PSU, the required power connectors, and any documented adapter conditions. Do not infer fit or power needs from the graphics-chip name alone: different cards using the same chip can have different cooler and connector designs. Airflow must remain workable after installation.

## PSU Compatibility

A compatible PSU needs appropriate electrical capacity, connections, physical size, and cable reach. Check the whole system's documented requirements and possible power excursions, rather than adding only the nominal CPU and GPU labels.

Confirm motherboard main power, CPU power, GPU power, and storage/accessory connections. CPU/EPS and GPU/PCIe cables are not interchangeable just because connectors look similar. Use only connections and adapters explicitly supported for the components involved.

Modular PSU cables are not universally interchangeable, including between some models of the same brand. Use the supplied cables or replacements explicitly approved for the exact PSU. Connector fit does not prove the wiring is correct. See [Corsair's modular cable compatibility explanation](https://www.corsair.com/us/en/explorer/diy-builder/power-supply-units/are-psu-cables-universal/).

Check PSU form factor, length, mounting arrangement, and available space for cables. A case that accepts one ATX PSU length may still be too cramped for another once drives and cabling are installed.

## Storage Compatibility

For a conventional SATA drive, verify a supported data connection, a suitable power connection, and space or mounting for the drive's physical format.

For an M.2 drive, check the exact motherboard slot's supported interface, keying, module length, and clearance. M.2 is a form factor; NVMe is a storage protocol used over PCIe. Some M.2 drives use SATA. A drive physically fitting a slot does not establish that the slot supports its interface.

Consult the board's lane-sharing rules. Using an M.2 slot may disable a SATA port or affect another expansion connection. Check boot support if the drive will hold the operating system, especially in older systems or when using adapters.

A faster PCIe drive can be limited by a slower supported link. That is a potential performance limit, not automatically incompatibility. [Kingston's SSD FAQ](https://www.kingston.com/en/ssd/ssd-faq) explains M.2 interface and slot-sharing considerations.

## Case and Motherboard Compatibility

ATX, Micro-ATX, and Mini-ITX describe common motherboard form factors. Match the exact motherboard dimensions and mounting pattern to the case's supported formats. A larger case often accepts smaller boards, but the case specification must confirm that support.

Check expansion-slot access, PSU placement, cable routing, and front-panel connections. A case's USB connector may require a motherboard header the chosen board does not provide. A listed front port is not proof it will function with every motherboard.

Install standoffs only where the motherboard requires them. Physical installation also depends on the selected GPU, cooler, and storage layout; successful motherboard fit alone is not a complete-system compatibility verdict.

## CPU Cooler Compatibility

Check the cooler's exact socket support and the required mounting kit. A manufacturer offering a compatible kit does not establish that the kit is included with a particular package.

For air coolers, compare total installed height with case clearance and check interference with RAM, motherboard heatsinks, and expansion slots. Raising a fan to clear tall memory can increase the cooler's total height. [Noctua's RAM-clearance guidance](https://www.noctua.at/en/support/faqs/what-is-the-ram-clearance-of-my-noctua-cpu-cooler) explains this interaction.

For liquid coolers, check radiator length, width, thickness, fan thickness, supported mounting position, and clearance for tubes, RAM, motherboard parts, and the GPU. “Radiator supported” does not guarantee every radiator of that nominal size fits in every position.

Mechanical compatibility and cooling capacity are separate checks. A cooler may mount correctly while being unsuitable for the CPU's sustained workload or the customer's noise target. Verify both before recommending the combination.

## Compatibility Uncertainty

Use these three outcomes carefully:

- **Verified compatible:** reliable specifications establish that every requirement relevant to the stated check is satisfied. State the scope and any conditions, such as a required BIOS version. RAM-generation compatibility alone does not prove a whole system compatible.
- **Verified incompatible:** verified facts establish a specific conflict, such as a DDR5 module and a motherboard explicitly supporting only DDR4. Name the conflict; do not infer it from missing information.
- **Insufficient information:** one or more required facts are missing, ambiguous, or unverified. Explain what is known and what additional evidence is needed. This does not mean incompatible.

Example: the catalog identifies a module as DDR5 but does not state the motherboard's supported RAM generation. The correct response is: “There is insufficient verified information to confirm this pairing. The memory is DDR5, but the motherboard's supported memory generation is not documented here.”

Elite AI's current compatibility capability reports insufficient verified information for existing product pairs because the required paired specifications are not established. This general reference must not override that outcome with knowledge inferred from a model name. A retrieved paragraph about a standard is not new evidence about an individual catalog item.

Request the exact missing specification or manufacturer support documentation. Even when several checks pass, keep the unresolved requirement visible instead of converting a partial check into a blanket assurance.
