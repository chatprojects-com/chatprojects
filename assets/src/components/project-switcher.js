/**
 * Project Switcher Component (Alpine.js)
 * Dropdown for switching between projects
 */

// Wait for Alpine to be available, then register component
document.addEventListener('alpine:init', () => {
  window.Alpine.data('projectSwitcher', (currentProjectId = null) => ({
  isOpen: false,
  searchQuery: '',
  currentProjectId: currentProjectId,
  projects: [],
  loading: false,


  init() {
    this.loadProjects();

    // Close on outside click
    document.addEventListener('click', (e) => {
      if (!this.$el.contains(e.target)) {
        this.isOpen = false;
      }
    });
  },

  async loadProjects() {
    this.loading = true;

    try {
      const response = await fetch(chatprData.ajax_url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          action: 'chatpr_get_projects',
          nonce: chatprData.nonce
        })
      });

      const data = await response.json();

      if (data.success) {
        this.projects = data.data;
      }
    } catch (error) {
      console.error('Failed to load projects:', error);
    } finally {
      this.loading = false;
    }
  },

  get filteredProjects() {
    if (!this.searchQuery) {
      return this.projects;
    }

    const query = this.searchQuery.toLowerCase();
    return this.projects.filter(project =>
      project.title.toLowerCase().includes(query)
    );
  },

  selectProject(projectId) {
    window.location.href = `?page=chatprojects&project_id=${projectId}`;
  },

  toggle() {
    this.isOpen = !this.isOpen;

    if (this.isOpen) {
      this.$nextTick(() => {
        this.$refs.searchInput?.focus();
      });
    }
  },

  // Open share modal via window event
  openShareModal() {
    this.isOpen = false; // Close dropdown
    window.dispatchEvent(new CustomEvent('open-share-modal'));
  }
  }));
});
