/**
 * l10n string registry for static extraction.
 *
 * This file is NOT imported anywhere at runtime. Its sole purpose is to
 * contain explicit t() calls with string literals so that translationtool.phar
 * can discover strings that are defined as plain strings in constants.js and
 * only wrapped with t() at render time.
 *
 * Cross-reference: keep this file in sync with constants.js (TABS[].label
 * and SORT_OPTIONS[*][].label). Whenever a label is added or changed in
 * constants.js, add/update the corresponding t() call here.
 */

import { t } from '@nextcloud/l10n'

// Tab labels (TABS[].label in constants.js)
t('talk_browser', 'Overview')
t('talk_browser', 'Images & Video')
t('talk_browser', 'Files')
t('talk_browser', 'Audio')
t('talk_browser', 'Voice notes')
t('talk_browser', 'Links')
t('talk_browser', 'Locations')
t('talk_browser', 'Other')

// Sort option labels (SORT_OPTIONS in constants.js)
t('talk_browser', 'Newest first')
t('talk_browser', 'Oldest first')
t('talk_browser', 'Name A→Z')
t('talk_browser', 'Name Z→A')
t('talk_browser', 'Largest first')
t('talk_browser', 'Smallest first')
t('talk_browser', 'Most shared')
t('talk_browser', 'Least shared')

// Settings panel strings (SettingsPanel.vue)
t('talk_browser', 'Settings')
t('talk_browser', 'View')
t('talk_browser', 'Show group conversations')
t('talk_browser', 'About')
t('talk_browser', 'Version')
t('talk_browser', 'Licence')
t('talk_browser', 'View on GitHub')
