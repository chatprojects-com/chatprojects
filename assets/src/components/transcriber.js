/**
 * Transcriber Component
 * Handles audio transcription with Whisper AI
 */

import { toast } from '../utils/toast.js';

// Wait for Alpine to be available, then register transcriber component
document.addEventListener('alpine:init', () => {
  window.Alpine.data('transcriber', (projectId = null) => ({
    projectId: projectId,
    selectedFile: null,
    language: '',
    enableRewrite: false,
    tone: '',
    transcribing: false,
    rewriting: false,
    saving: false,
    transcription: null,
    rewrittenText: null,
    duration: null,
    saveToDatabase: false,

    init() {
      // Component initialized
    },

    handleFileSelect(event) {
      const file = event.target.files?.[0];
      if (file) {
        this.selectFile(file);
      }
    },

    handleDrop(event) {
      event.preventDefault();
      const file = event.dataTransfer?.files?.[0];
      if (file) {
        this.selectFile(file);
      }
    },

    selectFile(file) {
      // Validate file type
      const allowedTypes = ['audio/mpeg', 'audio/mp4', 'audio/wav', 'audio/m4a', 'audio/webm', 'video/mp4', 'video/webm'];
      const isValidType = allowedTypes.includes(file.type) ||
                         file.name.match(/\.(mp3|mp4|wav|m4a|webm)$/i);

      if (!isValidType) {
        toast('Please select a valid audio or video file', 'error');
        return;
      }

      // Validate file size (max 25MB)
      const maxSize = 25 * 1024 * 1024;
      if (file.size > maxSize) {
        toast('File size exceeds 25MB limit', 'error');
        return;
      }

      this.selectedFile = file;

      // Reset previous results
      this.transcription = null;
      this.rewrittenText = null;
      this.duration = null;
    },

    clearFile() {
      this.selectedFile = null;
      this.transcription = null;
      this.rewrittenText = null;
      this.duration = null;
    },

    async transcribe() {
      if (!this.selectedFile) {
        toast('Please select an audio file first', 'error');
        return;
      }

      this.transcribing = true;
      const formData = new FormData();
      formData.append('action', 'chatpr_transcribe_audio');
      formData.append('nonce', chatprData.nonce);
      formData.append('project_id', this.projectId || '');
      formData.append('audio_file', this.selectedFile);  // Must match $_FILES['audio_file'] in PHP
      if (this.language) {
        formData.append('language', this.language);
      }

      try {
        const response = await fetch(chatprData.ajax_url, {
          method: 'POST',
          body: formData
        });

        const data = await response.json();

        if (data.success) {
          this.transcription = data.data.text || data.data.transcription;
          this.duration = data.data.duration;

          toast('Transcription completed successfully', 'success', 3000);

          // If rewrite is enabled, trigger it automatically
          if (this.enableRewrite && this.tone) {
            setTimeout(() => this.rewrite(), 500);
          }
        } else {
          toast(data.data?.message || 'Transcription failed', 'error');
        }
      } catch (error) {
        console.error('Transcription error:', error);
        toast('Failed to transcribe audio', 'error');
      } finally {
        this.transcribing = false;
      }
    },

    async rewrite() {
      if (!this.transcription) {
        toast('No transcription available to rewrite', 'error');
        return;
      }

      if (!this.tone) {
        toast('Please select a tone for rewriting', 'error');
        return;
      }

      this.rewriting = true;

      try {
        const response = await fetch(chatprData.ajax_url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: new URLSearchParams({
            action: 'chatpr_rewrite_transcription',
            nonce: chatprData.nonce,
            transcription: this.transcription,  // Must match PHP handler
            tone: this.tone,
            project_id: this.projectId || ''
          })
        });

        const data = await response.json();

        if (data.success) {
          this.rewrittenText = data.data.rewritten_text || data.data.text;
          toast('Text rewritten successfully', 'success', 2000);
        } else {
          toast(data.data?.message || 'Rewrite failed', 'error');
        }
      } catch (error) {
        console.error('Rewrite error:', error);
        toast('Failed to rewrite text', 'error');
      } finally {
        this.rewriting = false;
      }
    },

    async saveTranscription() {
      if (!this.transcription) {
        toast('No transcription available to save', 'error');
        return;
      }

      this.saving = true;

      try {
        const response = await fetch(chatprData.ajax_url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: new URLSearchParams({
            action: 'chatpr_save_transcription',
            nonce: chatprData.nonce,
            project_id: this.projectId || '',
            transcription: this.transcription,
            rewritten: this.rewrittenText || '',
            file_name: this.selectedFile?.name || 'transcription.txt',
            duration: this.duration || '',
            language: this.language || '',
            save_to_db: this.saveToDatabase ? '1' : '0'
          })
        });

        const data = await response.json();

        if (data.success) {
          toast('Transcription saved successfully', 'success', 3000);

          // Reset form after successful save
          setTimeout(() => {
            this.clearFile();
            this.saveToDatabase = false;
            this.tone = '';
          }, 1000);
        } else {
          toast(data.data?.message || 'Failed to save transcription', 'error');
        }
      } catch (error) {
        console.error('Save error:', error);
        toast('Failed to save transcription', 'error');
      } finally {
        this.saving = false;
      }
    },

    async saveToProject() {
      if (!this.transcription) {
        toast('No transcription available to save', 'error');
        return;
      }

      if (!this.projectId) {
        toast('No project selected', 'error');
        return;
      }

      this.saving = true;

      // Use rewritten text if available, otherwise use original transcription
      const textToSave = this.rewrittenText || this.transcription;

      // Generate filename from audio file or use default
      const audioName = this.selectedFile?.name?.replace(/\.[^/.]+$/, '') || 'transcription';
      const filename = `${audioName}_transcription.txt`;

      try {
        // Create a text file blob and upload it to the project
        const blob = new Blob([textToSave], { type: 'text/plain' });
        const file = new File([blob], filename, { type: 'text/plain' });

        const formData = new FormData();
        formData.append('action', 'chatpr_upload_file');
        formData.append('nonce', chatprData.nonce);
        formData.append('project_id', this.projectId);
        formData.append('file', file);

        const response = await fetch(chatprData.ajax_url, {
          method: 'POST',
          body: formData
        });

        const data = await response.json();

        if (data.success) {
          toast('Transcription saved to project successfully', 'success', 3000);

          // Reset form after successful save
          setTimeout(() => {
            this.clearFile();
            this.tone = '';
          }, 1000);
        } else {
          toast(data.data?.message || 'Failed to save to project', 'error');
        }
      } catch (error) {
        console.error('Save to project error:', error);
        toast('Failed to save to project', 'error');
      } finally {
        this.saving = false;
      }
    },

    copyText(text) {
      navigator.clipboard.writeText(text).then(() => {
        toast('Copied to clipboard', 'success', 2000);
      }).catch(() => {
        toast('Failed to copy to clipboard', 'error');
      });
    },

    formatFileSize(bytes) {
      if (!bytes || bytes === 0) return '0 Bytes';
      const k = 1024;
      const sizes = ['Bytes', 'KB', 'MB', 'GB'];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
    },

    formatDuration(seconds) {
      if (!seconds) return '';
      const mins = Math.floor(seconds / 60);
      const secs = Math.floor(seconds % 60);
      return `${mins}:${secs.toString().padStart(2, '0')}`;
    },

    triggerFileInput() {
      this.$refs.fileInput.click();
    }
  }));
});
