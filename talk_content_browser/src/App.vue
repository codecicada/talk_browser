<template>
	<NcContent app-name="talk_content_browser">
		<!-- Left navigation: conversation picker -->
		<NcAppNavigation>
			<template #list>
				<NcAppNavigationItem
					:name="t('talk_content_browser', 'Talk Content Browser')"
					:allow-collapse="false"
				/>
			</template>

			<template #footer>
				<div class="app-nav__conversation-picker">
					<p class="app-nav__label">
						{{ t('talk_content_browser', 'Conversation') }}
					</p>
					<ConversationPicker
						v-model="selectedToken"
						:conversations="conversations"
						:loading="conversationsLoading"
						@update:model-value="onConversationChange"
					/>
				</div>
			</template>
		</NcAppNavigation>

		<!-- Main content area -->
		<NcAppContent>
			<!-- Loading conversations -->
			<div v-if="conversationsLoading" class="app__loading">
				<NcLoadingIcon :size="48" />
				<p>{{ t('talk_content_browser', 'Loading conversations…') }}</p>
			</div>

			<!-- Error loading conversations -->
			<NcEmptyContent
				v-else-if="conversationsError"
				:name="t('talk_content_browser', 'Could not load conversations')"
				:description="conversationsError"
			>
				<template #icon>
					<span class="icon-error" />
				</template>
				<template #action>
					<NcButton @click="loadConversations">
						{{ t('talk_content_browser', 'Retry') }}
					</NcButton>
				</template>
			</NcEmptyContent>

			<!-- No conversation selected -->
			<NcEmptyContent
				v-else-if="!selectedToken"
				:name="t('talk_content_browser', 'Select a conversation')"
				:description="t('talk_content_browser', 'Choose a Talk conversation from the sidebar to browse its content')"
			>
				<template #icon>
					<span class="icon-talk" />
				</template>
			</NcEmptyContent>

			<!-- Content browser -->
			<template v-else>
				<div class="app__header">
					<h1 class="app__title">
						{{ selectedConversation?.displayName ?? '' }}
					</h1>
				</div>

				<ContentTabs v-model="activeTab">
					<template #default="{ activeTab: tab, searchQuery }">
						<!-- Overview -->
						<OverviewPanel
							v-if="tab === 'overview'"
							:overview-data="overviewData"
							:loading="itemsLoading"
							@go-to-tab="activeTab = $event"
						/>

						<!-- Images & Video -->
						<MediaGallery
							v-else-if="tab === 'media'"
							:items="items"
							:loading="itemsLoading"
							:loading-more="itemsLoadingMore"
							:has-more="itemsHasMore"
							:search="searchQuery"
							@load-more="loadMoreItems"
						/>

						<!-- Files -->
						<FileList
							v-else-if="tab === 'file'"
							:items="items"
							:loading="itemsLoading"
							:loading-more="itemsLoadingMore"
							:has-more="itemsHasMore"
							:search="searchQuery"
							@load-more="loadMoreItems"
						/>

						<!-- Audio -->
						<AudioList
							v-else-if="tab === 'audio'"
							:items="items"
							:loading="itemsLoading"
							:loading-more="itemsLoadingMore"
							:has-more="itemsHasMore"
							:search="searchQuery"
							:is-voice="false"
							@load-more="loadMoreItems"
						/>

						<!-- Voice notes -->
						<AudioList
							v-else-if="tab === 'voice'"
							:items="items"
							:loading="itemsLoading"
							:loading-more="itemsLoadingMore"
							:has-more="itemsHasMore"
							:search="searchQuery"
							:is-voice="true"
							@load-more="loadMoreItems"
						/>

						<!-- Links -->
						<LinkList
							v-else-if="tab === 'links'"
							:items="items"
							:loading="itemsLoading"
							:loading-more="itemsLoadingMore"
							:has-more="itemsHasMore"
							:link-scan-done="linkScanDone"
							:search="searchQuery"
							@load-more="loadMoreItems"
						/>

						<!-- Locations / deckcard / other / recording -->
						<GenericList
							v-else
							:items="items"
							:loading="itemsLoading"
							:loading-more="itemsLoadingMore"
							:has-more="itemsHasMore"
							:search="searchQuery"
							:object-type="tab"
							@load-more="loadMoreItems"
						/>
					</template>
				</ContentTabs>
			</template>
		</NcAppContent>
	</NcContent>
</template>

<script>
import {
	NcAppContent,
	NcAppNavigation,
	NcAppNavigationItem,
	NcButton,
	NcContent,
	NcEmptyContent,
	NcLoadingIcon,
} from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'
import { ref, watch } from 'vue'

import ConversationPicker from './components/ConversationPicker.vue'
import ContentTabs from './components/ContentTabs.vue'
import OverviewPanel from './components/OverviewPanel.vue'
import MediaGallery from './components/MediaGallery.vue'
import FileList from './components/FileList.vue'
import AudioList from './components/AudioList.vue'
import LinkList from './components/LinkList.vue'
import GenericList from './components/GenericList.vue'

import { useConversations } from './composables/useConversations.js'
import { useSharedItems } from './composables/useSharedItems.js'
import { fetchShareOverview } from './api/talk.js'

export default {
	name: 'App',

	components: {
		NcAppContent,
		NcAppNavigation,
		NcAppNavigationItem,
		NcButton,
		NcContent,
		NcEmptyContent,
		NcLoadingIcon,
		ConversationPicker,
		ContentTabs,
		OverviewPanel,
		MediaGallery,
		FileList,
		AudioList,
		LinkList,
		GenericList,
	},

	setup() {
		// ── Conversations ────────────────────────────────────────────────────
		const {
			conversations,
			loading: conversationsLoading,
			error: conversationsError,
			selectedToken,
			selectedConversation,
			load: loadConversations,
			selectConversation,
		} = useConversations()

		// ── Active tab ───────────────────────────────────────────────────────
		const activeTab = ref('overview')

		// ── Overview data (flat object keyed by type) ────────────────────────
		const overviewData = ref({})
		const overviewLoading = ref(false)

		// ── Shared items (for non-overview tabs) ─────────────────────────────
		const objectTypeRef = ref('overview')

		const {
			items,
			loading: itemsLoading,
			loadingMore: itemsLoadingMore,
			error: itemsError,
			hasMore: itemsHasMore,
			linkScanDone,
			load: loadItems,
			loadMore: loadMoreItems,
		} = useSharedItems(selectedToken, objectTypeRef)

		// When the conversation changes, reload overview and reset tab
		watch(selectedToken, async (token) => {
			if (!token) return
			activeTab.value = 'overview'
			overviewData.value = {}
			overviewLoading.value = true
			try {
				overviewData.value = await fetchShareOverview(token)
			} catch {
				overviewData.value = {}
			} finally {
				overviewLoading.value = false
			}
		})

		// When the active tab changes, update the objectType ref so useSharedItems reacts
		watch(activeTab, (tab) => {
			if (tab === 'overview') return
			objectTypeRef.value = tab
			loadItems()
		})

		return {
			// conversations
			conversations,
			conversationsLoading,
			conversationsError,
			selectedToken,
			selectedConversation,
			loadConversations,
			selectConversation,
			// tabs
			activeTab,
			// overview
			overviewData,
			overviewLoading,
			// items
			items,
			itemsLoading,
			itemsLoadingMore,
			itemsError,
			itemsHasMore,
			linkScanDone,
			loadMoreItems,
		}
	},

	mounted() {
		this.loadConversations()
	},

	methods: {
		t,

		onConversationChange(token) {
			this.activeTab = 'overview'
		},
	},
}
</script>

<style scoped>
.app__loading {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 16px;
	height: 100%;
	color: var(--color-text-maxcontrast);
}

.app__header {
	padding: 16px 16px 0;
}

.app__title {
	font-size: 20px;
	font-weight: 600;
	margin: 0 0 4px;
}

.app-nav__conversation-picker {
	padding: 12px;
}

.app-nav__label {
	font-size: 12px;
	font-weight: 600;
	color: var(--color-text-maxcontrast);
	text-transform: uppercase;
	letter-spacing: 0.05em;
	margin: 0 0 6px;
}
</style>
