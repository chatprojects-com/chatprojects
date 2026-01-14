/**
 * File Manager Component
 * Handles file uploads to Vector Stores with drag-and-drop support
 */

import { toast } from '../utils/toast.js';

// Wait for Alpine to be available, then register file manager component
document.addEventListener('alpine:init', () => {
  window.Alpine.data('fileManager', (projectId = null) => ({
    projectId: projectId,
    files: [],
    loading: false,
    uploading: false,
    uploadQueue: [],
    selectedFiles: [],
    selectAll: false,
    isDragging: false,

    init() {
      this.loadFiles();

      // Watch for select all changes
      this.$watch('selectAll', (value) => {
        if (value) {
          this.selectedFiles = this.files.map(f => f.id || f.file_id);
        } else {
          this.selectedFiles = [];
        }
      });

      // Watch for selectedFiles changes to update selectAll state
      this.$watch('selectedFiles', () => {
        if (this.selectedFiles.length === 0) {
          this.selectAll = false;
        } else if (this.selectedFiles.length === this.files.length && this.files.length > 0) {
          this.selectAll = true;
        }
      });
    },

    async loadFiles() {
      this.loading = true;

      try {
        const response = await fetch(chatprData.ajax_url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: new URLSearchParams({
            action: 'chatpr_list_files',
            nonce: chatprData.nonce,
            project_id: this.projectId || ''
          })
        });

        const data = await response.json();

        if (data.success) {
          this.files = data.data.files || [];
        } else {
          console.error('Failed to load files:', data.data);
        }
      } catch (error) {
        console.error('Error loading files:', error);
      } finally {
        this.loading = false;
      }
    },

    handleFileSelect(event) {
      const files = event.target.files;
      if (files && files.length > 0) {
        this.handleFiles(Array.from(files));
      }
      // Reset input to allow uploading the same file again
      event.target.value = '';
    },

    handleDrop(event) {
      event.preventDefault();
      this.isDragging = false;

      const files = event.dataTransfer?.files;
      if (files && files.length > 0) {
        this.handleFiles(Array.from(files));
      }
    },

    handleDragOver(event) {
      event.preventDefault();
      this.isDragging = true;
    },

    handleDragLeave(event) {
      event.preventDefault();
      this.isDragging = false;
    },

    handleFiles(files) {
      files.forEach(file => {
        if (this.validateFile(file)) {
          this.uploadFile(file);
        }
      });
    },

    validateFile(file) {
      // Check file size (max 50MB)
      const maxSize = 50 * 1024 * 1024;
      if (file.size > maxSize) {
        toast(`File "${file.name}" exceeds 50MB limit`, 'error');
        return false;
      }

      // Check allowed types
      const allowedTypes = [
        'application/pdf',
        'text/plain',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/csv',
        'application/json',
        'text/markdown',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
      ];

      if (!allowedTypes.includes(file.type) && !file.name.endsWith('.md')) {
        toast(`File type "${file.type}" is not allowed`, 'error');
        return false;
      }

      return true;
    },

    async uploadFile(file) {
      const uploadId = 'upload-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);

      // Add to upload queue
      const upload = {
        id: uploadId,
        name: file.name,
        progress: 0,
        status: 'uploading' // uploading, success, error
      };
      this.uploadQueue.push(upload);

      const formData = new FormData();
      formData.append('action', 'chatpr_upload_file');
      formData.append('nonce', chatprData.nonce);
      formData.append('project_id', this.projectId || '');
      formData.append('file', file);

      try {
        // Create XMLHttpRequest for progress tracking
        const xhr = new XMLHttpRequest();

        // Track upload progress
        xhr.upload.addEventListener('progress', (e) => {
          if (e.lengthComputable) {
            const percent = (e.loaded / e.total) * 100;
            const uploadItem = this.uploadQueue.find(u => u.id === uploadId);
            if (uploadItem) {
              uploadItem.progress = percent;
            }
          }
        });

        // Handle completion
        const uploadPromise = new Promise((resolve, reject) => {
          xhr.onload = () => {
            if (xhr.status === 200) {
              try {
                const response = JSON.parse(xhr.responseText);
                resolve(response);
              } catch (e) {
                reject(new Error('Invalid JSON response'));
              }
            } else {
              reject(new Error(`HTTP ${xhr.status}`));
            }
          };
          xhr.onerror = () => reject(new Error('Network error'));
        });

        xhr.open('POST', chatprData.ajax_url);
        xhr.send(formData);

        const response = await uploadPromise;

        if (response.success) {
          const uploadItem = this.uploadQueue.find(u => u.id === uploadId);
          if (uploadItem) {
            uploadItem.status = 'success';
            uploadItem.progress = 100;
          }

          toast(`File "${file.name}" uploaded successfully`, 'success', 3000);

          // Add file to list
          this.files.unshift(response.data.file);

          // Remove from upload queue after a delay
          setTimeout(() => {
            this.uploadQueue = this.uploadQueue.filter(u => u.id !== uploadId);
          }, 2000);
        } else {
          const uploadItem = this.uploadQueue.find(u => u.id === uploadId);
          if (uploadItem) {
            uploadItem.status = 'error';
          }

          const errorMessage = response.data?.message || 'Failed to upload file';
          toast(errorMessage, 'error');

          // Remove from queue after delay
          setTimeout(() => {
            this.uploadQueue = this.uploadQueue.filter(u => u.id !== uploadId);
          }, 3000);
        }
      } catch (error) {
        const uploadItem = this.uploadQueue.find(u => u.id === uploadId);
        if (uploadItem) {
          uploadItem.status = 'error';
        }

        console.error('Upload error:', error);
        toast(`Failed to upload "${file.name}"`, 'error');

        // Remove from queue after delay
        setTimeout(() => {
          this.uploadQueue = this.uploadQueue.filter(u => u.id !== uploadId);
        }, 3000);
      }
    },

    async deleteFile(fileId) {
      if (!confirm('Are you sure you want to delete this file? This action cannot be undone.')) {
        return;
      }

      try {
        const response = await fetch(chatprData.ajax_url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: new URLSearchParams({
            action: 'chatpr_delete_file',
            nonce: chatprData.nonce,
            file_id: fileId,
            project_id: this.projectId || ''
          })
        });

        const data = await response.json();

        if (data.success) {
          toast('File deleted successfully', 'success', 2000);

          // Remove file from list
          this.files = this.files.filter(f => (f.id || f.file_id) !== fileId);

          // Remove from selected if it was selected
          this.selectedFiles = this.selectedFiles.filter(id => id !== fileId);
        } else {
          toast(data.data?.message || 'Failed to delete file', 'error');
        }
      } catch (error) {
        console.error('Error deleting file:', error);
        toast('Failed to delete file', 'error');
      }
    },

    async bulkDeleteFiles() {
      const count = this.selectedFiles.length;

      if (count === 0) return;

      if (!confirm(`Are you sure you want to delete ${count} file${count > 1 ? 's' : ''}? This action cannot be undone.`)) {
        return;
      }

      const fileIds = [...this.selectedFiles];
      let deletedCount = 0;
      let failedCount = 0;

      for (const fileId of fileIds) {
        try {
          const response = await fetch(chatprData.ajax_url, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
              action: 'chatpr_delete_file',
              nonce: chatprData.nonce,
              file_id: fileId,
              project_id: this.projectId || ''
            })
          });

          const data = await response.json();

          if (data.success) {
            deletedCount++;
            // Remove from files array
            this.files = this.files.filter(f => (f.id || f.file_id) !== fileId);
          } else {
            failedCount++;
          }
        } catch (error) {
          console.error('Error deleting file:', error);
          failedCount++;
        }
      }

      // Clear selection
      this.selectedFiles = [];
      this.selectAll = false;

      // Show result
      if (deletedCount > 0) {
        toast(`${deletedCount} file${deletedCount > 1 ? 's' : ''} deleted successfully`, 'success');
      }
      if (failedCount > 0) {
        toast(`Failed to delete ${failedCount} file${failedCount > 1 ? 's' : ''}`, 'error');
      }
    },

    formatFileSize(bytes) {
      if (!bytes || bytes === 0) return '0 Bytes';
      const k = 1024;
      const sizes = ['Bytes', 'KB', 'MB', 'GB'];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
    },

    formatDate(dateString) {
      if (!dateString) return '';

      const date = new Date(dateString);
      const now = new Date();
      const diffTime = Math.abs(now - date);
      const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));

      if (diffDays === 0) {
        return 'Today at ' + date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
      } else if (diffDays === 1) {
        return 'Yesterday';
      } else if (diffDays < 7) {
        return `${diffDays} days ago`;
      } else {
        return date.toLocaleDateString();
      }
    },

    getFileId(file) {
      return file.id || file.file_id;
    },

    getFileSize(file) {
      return file.bytes || file.size || 0;
    },

    getFileDate(file) {
      return file.created_at || file.uploaded_at || '';
    },

    triggerFileInput() {
      this.$refs.fileInput.click();
    }
  }));
});
