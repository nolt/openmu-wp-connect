Demo data for the OpenMU WP Connect blocks, used until the OpenMU API is connected.
The shapes are those the API source returns to the blocks:

- `status.json`: same shape as OpenMU's `GET /api/status` (`state`, `players`).
- `ranking.json`: characters ordered by resets, then level.
- `characters.json`: public view of a character with its equipped items, keyed by slot.

Item, set, option and class names are the game's English names, as players know them.
