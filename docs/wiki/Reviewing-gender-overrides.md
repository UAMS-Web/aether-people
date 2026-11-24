Written against v0.5.4.

An editor changed a person's `gender` after Workday supplied a different value, and someone with edit access decides whether the override stands.

It needs the `edit people entries` permission.

## Steps

1. Open the "Gender Overrides to Review" widget on the dashboard. It lists people flagged for review, sorted by title, in the columns Person, Gender and In Workday.
2. Open a person with the link on their name to see the context. On the entry, the instructions of the Workday gender field begin with "Overridden" while the two values differ.
3. Choose one action on the widget row:
   - Keep override keeps the editor's value. The flag is removed, and a later change to the Workday value flags the person again.
   - Use Workday value sets `gender` to `workday_gender` and removes the flag.
4. The page confirms with "Gender override confirmed." or "Gender set to the value in Workday."

The widget shows nothing for people outside the editing scope of a scoped user. Neither action sends mail.

## Reference

- [Control Panel tools](Control-Panel-tools.md#gender-overrides): how the flag is set and cleared, and the two routes.
