<?php
/**
 * File Manager Template
 *
 * @package ChatProjects
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

call_user_func(function () {
    $project_id = get_the_ID();
    $vector_store_id = get_post_meta($project_id, '_cp_vector_store_id', true);
    ?>

<div id="vp-file-manager" class="p-6" x-data="fileManager(<?php echo esc_js($project_id); ?>)" @files-imported.window="loadFiles()">
    <!-- File Header -->
    <div class="mb-6">
        <div class="flex items-center justify-between mb-2">
            <h3 class="text-lg font-semibold text-neutral-900 dark:text-white"><?php esc_html_e('Vector Store Files', 'chatprojects'); ?></h3>
            <?php if ($vector_store_id) : ?>
                <span class="px-3 py-1 text-xs font-mono bg-neutral-100 dark:bg-dark-hover text-neutral-700 dark:text-neutral-300 rounded-md"><?php echo esc_html($vector_store_id); ?></span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Upload Area -->
    <div class="mb-6">
        <div
            @click="triggerFileInput()"
            @drop="handleDrop($event)"
            @dragover="handleDragOver($event)"
            @dragleave="handleDragLeave($event)"
            :class="{ 'border-blue-500 bg-blue-50 dark:bg-blue-900/20': isDragging }"
            class="relative border-2 border-dashed border-neutral-300 dark:border-neutral-700 rounded-lg p-8 text-center cursor-pointer hover:border-blue-400 dark:hover:border-blue-500 transition-colors"
        >
            <input
                type="file"
                x-ref="fileInput"
                @change="handleFileSelect($event)"
                multiple
                accept=".pdf,.docx,.doc,.txt,.xls,.xlsx,.md,.csv,.json,.xml,.html,.css,.js,.py,.php,.java,.cpp"
                class="hidden"
            />

            <svg class="w-12 h-12 mx-auto mb-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
            </svg>

            <h4 class="mb-2 text-base font-semibold text-neutral-900 dark:text-white"><?php esc_html_e('Upload Files', 'chatprojects'); ?></h4>
            <p class="mb-4 text-sm text-neutral-600 dark:text-neutral-400"><?php esc_html_e('Drag and drop files here, or click to select', 'chatprojects'); ?></p>

            <span class="inline-block px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors">
                <?php esc_html_e('Browse Files', 'chatprojects'); ?>
            </span>

            <p class="mt-4 text-xs text-neutral-500 dark:text-neutral-400">
                <?php
                $max_size = get_option('chatprojects_max_file_size', 50);
                printf(
                    /* translators: %s: Maximum file size in megabytes */
                    esc_html__('Maximum file size: %s MB', 'chatprojects'),
                    esc_html($max_size)
                );
                ?>
            </p>
            <p class="mt-2 text-xs text-neutral-500 dark:text-neutral-400">
                <?php esc_html_e('Supported: PDF, DOC, DOCX, TXT, MD, CSV, JSON, XML, HTML, CSS, JS, PY, PHP', 'chatprojects'); ?>
            </p>
            <p class="mt-1 text-xs text-neutral-400 dark:text-neutral-500">
                <?php esc_html_e('Spreadsheets (XLS, XLSX) available in Pro', 'chatprojects'); ?>
            </p>
        </div>
    </div>

    <!-- Media Library Section -->
    <div class="mb-6" x-data="mediaLibraryPicker(<?php echo esc_js($project_id); ?>)">
        <div class="flex items-center gap-4">
            <button
                @click="openMediaPicker()"
                :disabled="importing"
                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-neutral-700 dark:text-neutral-200 bg-white dark:bg-dark-hover border border-neutral-300 dark:border-dark-border hover:bg-neutral-50 dark:hover:bg-neutral-700 rounded-lg transition-colors disabled:opacity-50"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <span x-show="!importing"><?php esc_html_e('Select from Media Library', 'chatprojects'); ?></span>
                <span x-show="importing" x-cloak><?php esc_html_e('Importing...', 'chatprojects'); ?></span>
            </button>
        </div>

        <!-- Media Library Import Progress -->
        <template x-if="importProgress.length > 0">
            <div class="mt-4 space-y-2">
                <template x-for="(item, index) in importProgress" :key="index">
                    <div class="flex items-center gap-3 p-2 bg-neutral-100 dark:bg-dark-hover rounded-lg">
                        <span :class="getStatusColor(item.status)" class="text-lg" x-text="getStatusIcon(item.status)"></span>
                        <span class="text-sm text-neutral-700 dark:text-neutral-300 truncate" x-text="item.name"></span>
                        <span x-show="item.error" class="text-xs text-red-500" x-text="item.error"></span>
                    </div>
                </template>
            </div>
        </template>

        <!-- Error Message -->
        <template x-if="error">
            <div class="mt-4 p-3 bg-red-100 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                <p class="text-sm text-red-700 dark:text-red-400" x-text="error"></p>
            </div>
        </template>
    </div>

    <!-- Upload Progress -->
    <template x-if="uploadQueue.length > 0">
        <div class="mb-6 space-y-2">
            <template x-for="upload in uploadQueue" :key="upload.id">
                <div class="p-3 bg-neutral-100 dark:bg-dark-hover rounded-lg">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium text-neutral-900 dark:text-white truncate" x-text="upload.name"></span>
                        <span class="text-xs text-neutral-500" x-text="Math.round(upload.progress) + '%'"></span>
                    </div>
                    <div class="w-full h-2 bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">
                        <div
                            class="h-full transition-all duration-300"
                            :class="{
                                'bg-blue-500': upload.status === 'uploading',
                                'bg-green-500': upload.status === 'success',
                                'bg-red-500': upload.status === 'error'
                            }"
                            :style="{ width: upload.progress + '%' }"
                        ></div>
                    </div>
                </div>
            </template>
        </div>
    </template>

    <!-- Bulk Actions -->
    <template x-if="files.length > 0">
        <div class="mb-4 flex items-center justify-between">
            <label class="flex items-center gap-2 cursor-pointer">
                <input
                    type="checkbox"
                    x-model="selectAll"
                    class="w-4 h-4 text-blue-600 border-neutral-300 dark:border-neutral-600 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:bg-neutral-700"
                />
                <span class="text-sm text-neutral-700 dark:text-neutral-300"><?php esc_html_e('Select All', 'chatprojects'); ?></span>
            </label>

            <template x-if="selectedFiles.length > 0">
                <button
                    @click="bulkDeleteFiles()"
                    class="px-3 py-1.5 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors"
                >
                    <?php esc_html_e('Delete Selected', 'chatprojects'); ?> (<span x-text="selectedFiles.length"></span>)
                </button>
            </template>
        </div>
    </template>

    <!-- File List -->
    <div>
        <h4 class="mb-3 text-sm font-semibold text-neutral-900 dark:text-white"><?php esc_html_e('Uploaded Files', 'chatprojects'); ?></h4>

        <!-- Loading state -->
        <template x-if="loading">
            <div class="flex items-center justify-center py-12">
                <svg class="animate-spin h-8 w-8 text-blue-500" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
        </template>

        <!-- Empty state -->
        <template x-if="!loading && files.length === 0">
            <div class="flex flex-col items-center justify-center py-12 text-neutral-500 dark:text-neutral-400">
                <svg class="w-16 h-16 mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
                <p class="text-sm"><?php esc_html_e('No files uploaded yet', 'chatprojects'); ?></p>
            </div>
        </template>

        <!-- File list -->
        <template x-if="!loading && files.length > 0">
            <ul class="space-y-2">
                <template x-for="file in files" :key="getFileId(file)">
                    <li class="flex items-center gap-3 p-3 bg-white dark:bg-dark-hover border border-neutral-200 dark:border-dark-border rounded-lg hover:shadow-md transition-shadow">
                        <!-- Checkbox -->
                        <input
                            type="checkbox"
                            :value="getFileId(file)"
                            x-model="selectedFiles"
                            class="w-4 h-4 text-blue-600 border-neutral-300 dark:border-neutral-600 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:bg-neutral-700"
                        />

                        <!-- File icon -->
                        <div class="flex-shrink-0">
                            <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            </svg>
                        </div>

                        <!-- File info -->
                        <div class="flex-1 min-w-0">
                            <h5 class="text-sm font-medium text-neutral-900 dark:text-white truncate" x-text="file.filename"></h5>
                            <p class="text-xs text-neutral-500 dark:text-neutral-400">
                                <span x-text="formatFileSize(getFileSize(file))"></span>
                                •
                                <span x-text="formatDate(getFileDate(file))"></span>
                            </p>
                        </div>

                        <!-- Delete button -->
                        <button
                            @click="deleteFile(getFileId(file))"
                            class="flex-shrink-0 p-2 text-red-600 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                            title="<?php esc_attr_e('Delete file', 'chatprojects'); ?>"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </li>
                </template>
            </ul>
        </template>
    </div>
</div>
<?php
});
