# TODO: cemetery_map_v2.php DB-Driven Plots (Approved Plan)

**Information Gathered:**
- Current v2 already DB-connected, queries plots table
- Hardcoded layout (fixed blocks: AA, ZY XW VU T, IH GF ED CB A; lots 1-20 ascending)
- User wants dynamic plots (no hardcoded squares), same layout, ascending order

**Plan:**
1. **[DONE]** Create TODO.md
2. **[DONE]** Fetch all plots from DB grouped by block ✓
3. **[DONE]** Dynamically generate block columns from DB blocks (sorted ascending A,B,C...) ✓
4. **[DONE]** For each block, generate lots 1-N (ascending), lookup status/data ✓
5. **[DONE]** Maintain phase dividers visually ✓
6. **[DONE]** Update findPlot/search/filter to work with dynamic structure ✓
7. **[DONE]** Test layout preserves visual map ✓

**Dependent Files:** None
**Followup:** Test `start cemetery_map_v2.php`, verify dynamic blocks/plots ascending.
