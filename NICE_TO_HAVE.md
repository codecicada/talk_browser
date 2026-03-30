# Talk Browser — Nice to Have

A prioritized backlog of missing features and polish items.

---

## High Value / User-Facing

1. **Lightbox / in-app media viewer**
   Clicking an image or video currently opens Nextcloud Files in a new tab. A modal lightbox viewer would be far more polished and keep users in context.

2. **Real conversation avatars**
   `ConversationPicker` uses generic CSS icon classes (`icon-user`, `icon-contacts`). The Talk API returns avatar data; replacing these with `NcAvatar` would look much more native.

3. **Sidebar conversation search**
   No way to filter conversations by name. With many rooms this becomes painful quickly.

4. **Link deduplication**
   If the same URL appears in multiple messages it shows as a separate entry each time. URLs should be deduplicated, grouping multiple occurrences under a single entry.

5. **`loadingMore` spinner**
   All list components silently ignore the `loadingMore` prop that `App.vue` already passes down. There is no visual feedback while paginating to the next page.

6. **Error UI for item loading**
   `itemsError` is computed and returned by `useSharedItems` but never rendered anywhere. Item-loading failures are silently swallowed with no message to the user.

---

## Medium Value / Polish

7. **WebDAV path encoding bug in `AudioList`** *(actual bug)*
   `encodeURIComponent` is applied to the full path string, which encodes `/` separators and breaks audio streaming for files in subdirectories. The fix is to encode each path segment individually.

8. **Image / audio `@error` fallback**
   Broken thumbnails and failed audio streams have no fallback UI. A placeholder icon or error message should be shown when the resource fails to load.

9. **`formatDate` inconsistency**
   `OverviewPanel` omits the year when formatting dates; all other components include it. Should be unified across the app.

10. **Remove debug `console.log`**
    Debug `console.log` / `console.error` statements left in `App.vue`'s `selectedToken` watcher should be removed before release.

11. **Dead code cleanup**
    The following are defined but never used and should be removed:
    - `conversationTypeLabel()` in `constants.js` (never imported)
    - `selectConversation` in `App.vue` (never called)
    - `linkMessages` reactive state in `useSharedItems.js`
    - `.media-gallery__video-thumb` CSS class in `MediaGallery.vue`

12. **Map link for geo-locations**
    `GenericList` displays raw `latitude, longitude` coordinates with no actionable link. Should include an OpenStreetMap (or similar) link so users can open the location on a map.

---

## Lower Value / Nice Polish

13. **ARIA accessibility**
    The tab bar lacks `role="tablist"` / `role="tab"` / `role="tabpanel"` semantics. The conversation list lacks keyboard navigation support.

14. **File sort controls**
    `FileList` has no option to sort items by name, date, or size.

15. **Unread counts and last message in sidebar**
    The Talk API returns unread message counts and last-message previews for each conversation, but these are not displayed in `ConversationPicker`.
