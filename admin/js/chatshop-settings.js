/**
 * ChatShop Settings JavaScript
 *
 * Handles frontend interactions for the settings interface including:
 * - Real-time field validation
 * - Conditional field display
 * - API testing
 * - Import/export functionality
 * - Form enhancements
 *
 * File: admin/js/chatshop-settings.js
 *
 * @package ChatShop
 * @subpackage Admin
 * @since 1.0.0
 */

(function ($) {
  "use strict";

  /**
   * ChatShop Settings Manager
   */
  const ChatShopSettings = {
    /**
     * Initialize the settings interface
     */
    init() {
      this.bindEvents();
      this.initializeFields();
      this.handleConditionalFields();
      this.initColorPickers();
      this.initCodeEditors();
      this.initRepeaterFields();
      this.setupValidation();
    },

    /**
     * Bind event handlers
     */
    bindEvents() {
      // Tab navigation
      $(document).on(
        "click",
        ".chatshop-nav-tab",
        this.handleTabClick.bind(this)
      );

      // Form submission
      $(".chatshop-settings-form").on(
        "submit",
        this.handleFormSubmit.bind(this)
      );

      // Field validation
      $(document).on(
        "blur",
        ".chatshop-settings-form input, .chatshop-settings-form textarea, .chatshop-settings-form select",
        this.validateField.bind(this)
      );

      // Password toggles
      $(document).on(
        "click",
        ".chatshop-password-toggle",
        this.togglePasswordVisibility.bind(this)
      );

      // API testing
      $(document).on(
        "click",
        ".chatshop-test-api",
        this.testApiConnection.bind(this)
      );

      // Modal handling
      $(document).on(
        "click",
        ".chatshop-import-settings",
        this.openImportModal.bind(this)
      );
      $(document).on(
        "click",
        ".chatshop-reset-tab",
        this.openResetModal.bind(this)
      );
      $(document).on(
        "click",
        ".chatshop-validate-settings",
        this.validateAllSettings.bind(this)
      );
      $(document).on(
        "click",
        ".chatshop-modal-close",
        this.closeModal.bind(this)
      );

      // Export functionality
      $(document).on(
        "click",
        ".chatshop-export-settings",
        this.exportSettings.bind(this)
      );

      // File uploads
      $(document).on(
        "change",
        ".chatshop-file-upload",
        this.handleFileUpload.bind(this)
      );
      $(document).on(
        "click",
        ".chatshop-remove-file",
        this.removeFile.bind(this)
      );

      // Repeater fields
      $(document).on(
        "click",
        ".chatshop-add-repeater-item",
        this.addRepeaterItem.bind(this)
      );
      $(document).on(
        "click",
        ".chatshop-remove-repeater-item",
        this.removeRepeaterItem.bind(this)
      );

      // Quick actions
      $(document).on(
        "click",
        ".chatshop-clear-cache",
        this.clearCache.bind(this)
      );
      $(document).on(
        "click",
        ".chatshop-test-connections",
        this.testAllConnections.bind(this)
      );
      $(document).on("click", ".chatshop-sync-data", this.syncData.bind(this));

      // Conditional field dependencies
      $(document).on(
        "change",
        "[data-field-conditions]",
        this.handleFieldConditions.bind(this)
      );

      // Auto-save for certain fields
      $(document).on(
        "change",
        ".chatshop-auto-save",
        this.autoSaveField.bind(this)
      );
    },

    /**
     * Initialize fields with special functionality
     */
    initializeFields() {
      // Initialize toggles
      $(".chatshop-toggle").each(function () {
        const $toggle = $(this);
        const $wrapper = $toggle.closest(".chatshop-field-wrapper");

        $toggle.on("change", function () {
          $wrapper.toggleClass("toggle-on", this.checked);
        });

        // Set initial state
        $wrapper.toggleClass("toggle-on", $toggle.is(":checked"));
      });

      // Initialize number fields with spinners
      $('input[type="number"]').each(function () {
        const $input = $(this);
        const min = parseFloat($input.attr("min"));
        const max = parseFloat($input.attr("max"));
        const step = parseFloat($input.attr("step")) || 1;

        if (!isNaN(min) || !isNaN(max)) {
          $input.on("input", function () {
            let value = parseFloat(this.value);

            if (!isNaN(min) && value < min) {
              this.value = min;
            }

            if (!isNaN(max) && value > max) {
              this.value = max;
            }
          });
        }
      });

      // Initialize select2 for enhanced selects
      if ($.fn.select2) {
        $(".chatshop-enhanced-select").select2({
          width: "100%",
          placeholder: function () {
            return $(this).attr("placeholder");
          },
          allowClear: true,
        });
      }
    },

    /**
     * Handle conditional field display
     */
    handleConditionalFields() {
      $("[data-depends-on]").each(function () {
        const $field = $(this);
        const dependsOn = $field.data("depends-on");
        const dependsValue = $field.data("depends-value") || true;
        const $dependency = $(`[name*="${dependsOn}"]`);

        if ($dependency.length) {
          const updateVisibility = () => {
            let currentValue;

            if ($dependency.is(":checkbox")) {
              currentValue = $dependency.is(":checked");
            } else if ($dependency.is(":radio")) {
              currentValue = $dependency.filter(":checked").val();
            } else {
              currentValue = $dependency.val();
            }

            const shouldShow = currentValue == dependsValue;
            $field.toggleClass("chatshop-hidden", !shouldShow);
          };

          $dependency.on("change", updateVisibility);
          updateVisibility(); // Initial check
        }
      });
    },

    /**
     * Initialize color pickers
     */
    initColorPickers() {
      if ($.fn.wpColorPicker) {
        $(".chatshop-color-picker").wpColorPicker({
          change: function (event, ui) {
            $(this).trigger("chatshop:color-changed", [ui.color.toString()]);
          },
        });
      }
    },

    /**
     * Initialize code editors
     */
    initCodeEditors() {
      $(".chatshop-code-editor").each(function () {
        const $textarea = $(this);
        const language = $textarea.data("language") || "javascript";

        // Simple syntax highlighting enhancement
        $textarea.on("input", function () {
          // Basic syntax validation for JSON
          if (language === "json") {
            try {
              JSON.parse(this.value);
              $textarea.removeClass("error");
            } catch (e) {
              $textarea.addClass("error");
            }
          }
        });
      });
    },

    /**
     * Initialize repeater fields
     */
    initRepeaterFields() {
      $(".chatshop-repeater-wrapper").each(function () {
        const $wrapper = $(this);
        const maxItems = parseInt($wrapper.data("max-items")) || 0;

        const updateAddButton = () => {
          const currentCount = $wrapper.find(".chatshop-repeater-item").length;
          const $addButton = $wrapper.find(".chatshop-add-repeater-item");

          if (maxItems > 0 && currentCount >= maxItems) {
            $addButton.prop("disabled", true);
          } else {
            $addButton.prop("disabled", false);
          }
        };

        $wrapper.on("chatshop:repeater-changed", updateAddButton);
        updateAddButton();
      });
    },

    /**
     * Setup field validation
     */
    setupValidation() {
      // Real-time validation for specific field types
      $('input[type="email"]').on("input", function () {
        const email = this.value;
        const isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        $(this).toggleClass("invalid", email && !isValid);
      });

      $('input[type="url"]').on("input", function () {
        const url = this.value;
        const isValid = /^https?:\/\/.+\..+/.test(url);
        $(this).toggleClass("invalid", url && !isValid);
      });

      // Custom validation for API keys
      $(".chatshop-api-key").on("input", function () {
        const $field = $(this);
        const value = this.value;
        const format = $field.data("format");

        if (format && value) {
          let isValid = true;

          switch (format) {
            case "paystack":
              isValid = /^sk_(test|live)_[a-zA-Z0-9]+$/.test(value);
              break;
            case "paypal":
              isValid = value.length >= 20;
              break;
          }

          $field.toggleClass("invalid", !isValid);
        }
      });
    },

    /**
     * Handle tab clicks
     */
    handleTabClick(e) {
      e.preventDefault();

      const $tab = $(e.currentTarget);
      const tabKey = $tab.data("tab");

      // Update URL without page reload
      const url = new URL(window.location);
      url.searchParams.set("tab", tabKey);
      history.pushState(null, "", url);

      // Navigate to tab
      window.location.href = $tab.attr("href");
    },

    /**
     * Handle form submission
     */
    handleFormSubmit(e) {
      const $form = $(e.target);
      const $submitButton = $form.find('button[type="submit"]');

      // Show loading state
      $submitButton.prop("disabled", true);
      $submitButton
        .find(".dashicons")
        .removeClass("dashicons-yes")
        .addClass("dashicons-update");

      // Validate form before submission
      const isValid = this.validateForm($form);

      if (!isValid) {
        e.preventDefault();
        $submitButton.prop("disabled", false);
        $submitButton
          .find(".dashicons")
          .removeClass("dashicons-update")
          .addClass("dashicons-yes");

        this.showNotice(
          "Please fix the validation errors before saving.",
          "error"
        );
        return false;
      }

      // Show success message preparation
      setTimeout(() => {
        this.showNotice("Settings saved successfully!", "success");
      }, 100);
    },

    /**
     * Validate entire form
     */
    validateForm($form) {
      let isValid = true;
      const $fields = $form.find("input, textarea, select").not(":disabled");

      $fields.each((index, field) => {
        if (!this.validateSingleField($(field))) {
          isValid = false;
        }
      });

      return isValid;
    },

    /**
     * Validate individual field
     */
    validateField(e) {
      const $field = $(e.target);
      this.validateSingleField($field);
    },

    /**
     * Validate single field
     */
    validateSingleField($field) {
      const fieldType =
        $field.attr("type") || $field.prop("tagName").toLowerCase();
      const value = $field.val();
      const isRequired = $field.prop("required") || $field.hasClass("required");

      let isValid = true;
      let message = "";

      // Required field check
      if (isRequired && !value) {
        isValid = false;
        message = "This field is required.";
      }

      // Type-specific validation
      if (isValid && value) {
        switch (fieldType) {
          case "email":
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
              isValid = false;
              message = "Please enter a valid email address.";
            }
            break;

          case "url":
            if (!/^https?:\/\/.+\..+/.test(value)) {
              isValid = false;
              message = "Please enter a valid URL.";
            }
            break;

          case "number":
            const min = parseFloat($field.attr("min"));
            const max = parseFloat($field.attr("max"));
            const num = parseFloat(value);

            if (isNaN(num)) {
              isValid = false;
              message = "Please enter a valid number.";
            } else if (!isNaN(min) && num < min) {
              isValid = false;
              message = `Value must be at least ${min}.`;
            } else if (!isNaN(max) && num > max) {
              isValid = false;
              message = `Value must not exceed ${max}.`;
            }
            break;
        }
      }

      // Update field state
      $field.toggleClass("invalid", !isValid);

      const $wrapper = $field.closest(".chatshop-field-wrapper");
      const $validation = $wrapper.find(".chatshop-field-validation");

      if (!isValid) {
        $validation.html(`<span class="error">${message}</span>`).show();
      } else {
        $validation.hide();
      }

      return isValid;
    },

    /**
     * Toggle password visibility
     */
    togglePasswordVisibility(e) {
      e.preventDefault();

      const $button = $(e.currentTarget);
      const targetId = $button.data("target");
      const $field = $(`#${targetId}`);

      if ($field.attr("type") === "password") {
        $field.attr("type", "text");
        $button.text("Hide");
      } else {
        $field.attr("type", "password");
        $button.text("Show");
      }
    },

    /**
     * Test API connection
     */
    testApiConnection(e) {
      e.preventDefault();

      const $button = $(e.currentTarget);
      const component = $button.data("component");
      const fieldName = $button.data("field");

      // Get API data from form
      const apiData = this.getApiDataForComponent(component);

      $button.prop("disabled", true);
      $button.html('<span class="spinner is-active"></span> Testing...');

      $.ajax({
        url: chatshopSettings.ajaxUrl,
        type: "POST",
        data: {
          action: "chatshop_test_connection",
          component: component,
          api_data: apiData,
          nonce: chatshopSettings.nonce,
        },
        success: (response) => {
          if (response.success) {
            this.showNotice(response.data.message, "success");
          } else {
            this.showNotice(
              response.data.message || "Connection test failed.",
              "error"
            );
          }
        },
        error: () => {
          this.showNotice(
            "Connection test failed due to network error.",
            "error"
          );
        },
        complete: () => {
          $button.prop("disabled", false);
          $button.html(
            '<span class="dashicons dashicons-admin-links"></span> Test Connection'
          );
        },
      });
    },

    /**
     * Get API data for component testing
     */
    getApiDataForComponent(component) {
      const apiData = {};

      // Collect relevant API fields for the component
      $(`.chatshop-settings-form [name*="${component}"]`).each(function () {
        const $field = $(this);
        const name = $field.attr("name");
        const matches = name.match(/\[([^\]]+)\]$/);

        if (matches) {
          const fieldKey = matches[1];
          if (
            fieldKey.includes("key") ||
            fieldKey.includes("token") ||
            fieldKey.includes("secret")
          ) {
            apiData[fieldKey] = $field.val();
          }
        }
      });

      return apiData;
    },

    /**
     * Open import modal
     */
    openImportModal(e) {
      e.preventDefault();
      $("#chatshop-import-modal").show();
    },

    /**
     * Open reset modal
     */
    openResetModal(e) {
      e.preventDefault();

      const tab = $(e.currentTarget).data("tab");
      $("#reset_current_tab").val(tab);
      $("#chatshop-reset-modal").show();
    },

    /**
     * Validate all settings
     */
    validateAllSettings(e) {
      e.preventDefault();

      $("#chatshop-validation-modal").show();

      const $form = $(".chatshop-settings-form");
      const formData = $form.serialize();

      $.ajax({
        url: chatshopSettings.ajaxUrl,
        type: "POST",
        data: {
          action: "chatshop_validate_all_settings",
          form_data: formData,
          nonce: chatshopSettings.nonce,
        },
        success: (response) => {
          $("#validation-results").html(response.data);
        },
        error: () => {
          $("#validation-results").html(
            '<div class="error">Validation failed due to network error.</div>'
          );
        },
      });
    },

    /**
     * Close modal
     */
    closeModal(e) {
      e.preventDefault();
      $(e.currentTarget).closest(".chatshop-modal").hide();
    },

    /**
     * Export settings
     */
    exportSettings(e) {
      e.preventDefault();

      const nonce = $(e.currentTarget).data("nonce");
      const url = `${chatshopSettings.ajaxUrl}?action=chatshop_export_settings&nonce=${nonce}`;

      // Create temporary link and trigger download
      const link = document.createElement("a");
      link.href = url;
      link.download = `chatshop-settings-${
        new Date().toISOString().split("T")[0]
      }.json`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);

      this.showNotice("Settings exported successfully!", "success");
    },

    /**
     * Handle file uploads
     */
    handleFileUpload(e) {
      const $input = $(e.target);
      const file = e.target.files[0];

      if (!file) return;

      const fieldName = $input.data("field-name");
      const maxSize = $input.data("max-size") || "2MB";
      const maxSizeBytes = this.parseFileSize(maxSize);

      if (file.size > maxSizeBytes) {
        this.showNotice(
          `File is too large. Maximum size is ${maxSize}.`,
          "error"
        );
        $input.val("");
        return;
      }

      const formData = new FormData();
      formData.append("file", file);
      formData.append("field_name", fieldName);
      formData.append("action", "chatshop_upload_file");
      formData.append("nonce", chatshopSettings.nonce);

      $.ajax({
        url: chatshopSettings.ajaxUrl,
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        success: (response) => {
          if (response.success) {
            $(`#hidden_${fieldName}`).val(response.data.url);
            this.showNotice("File uploaded successfully!", "success");
          } else {
            this.showNotice(response.data || "Upload failed.", "error");
          }
        },
        error: () => {
          this.showNotice("Upload failed due to network error.", "error");
        },
      });
    },

    /**
     * Remove file
     */
    removeFile(e) {
      e.preventDefault();

      const fieldName = $(e.currentTarget).data("field-name");
      $(`#hidden_${fieldName}`).val("");
      $(e.currentTarget).closest(".chatshop-current-file").remove();

      this.showNotice("File removed.", "success");
    },

    /**
     * Add repeater item
     */
    addRepeaterItem(e) {
      e.preventDefault();

      const $button = $(e.currentTarget);
      const $wrapper = $button.closest(".chatshop-repeater-wrapper");
      const $container = $wrapper.find(".chatshop-repeater-items");
      const template = $wrapper.find(".chatshop-repeater-template").html();

      const currentCount = $container.find(".chatshop-repeater-item").length;
      const newIndex = currentCount;

      const newItem = template.replace(/\{\{INDEX\}\}/g, newIndex);
      $container.append(newItem);

      $wrapper.trigger("chatshop:repeater-changed");
    },

    /**
     * Remove repeater item
     */
    removeRepeaterItem(e) {
      e.preventDefault();

      const $button = $(e.currentTarget);
      const $item = $button.closest(".chatshop-repeater-item");
      const $wrapper = $button.closest(".chatshop-repeater-wrapper");

      $item.remove();
      $wrapper.trigger("chatshop:repeater-changed");
    },

    /**
     * Clear cache
     */
    clearCache(e) {
      e.preventDefault();

      const $button = $(e.currentTarget);
      $button.prop("disabled", true);

      $.ajax({
        url: chatshopSettings.ajaxUrl,
        type: "POST",
        data: {
          action: "chatshop_clear_cache",
          nonce: chatshopSettings.nonce,
        },
        success: (response) => {
          if (response.success) {
            this.showNotice("Cache cleared successfully!", "success");
          } else {
            this.showNotice("Failed to clear cache.", "error");
          }
        },
        complete: () => {
          $button.prop("disabled", false);
        },
      });
    },

    /**
     * Test all connections
     */
    testAllConnections(e) {
      e.preventDefault();

      const $button = $(e.currentTarget);
      $button.prop("disabled", true);

      // Test all enabled components
      const components = ["paystack", "whatsapp"];
      let completedTests = 0;
      let passedTests = 0;

      components.forEach((component) => {
        const apiData = this.getApiDataForComponent(component);

        $.ajax({
          url: chatshopSettings.ajaxUrl,
          type: "POST",
          data: {
            action: "chatshop_test_connection",
            component: component,
            api_data: apiData,
            nonce: chatshopSettings.nonce,
          },
          success: (response) => {
            if (response.success) {
              passedTests++;
            }
          },
          complete: () => {
            completedTests++;

            if (completedTests === components.length) {
              this.showNotice(
                `Connection tests completed: ${passedTests}/${components.length} passed.`,
                passedTests === components.length ? "success" : "warning"
              );
              $button.prop("disabled", false);
            }
          },
        });
      });
    },

    /**
     * Sync data
     */
    syncData(e) {
      e.preventDefault();

      const $button = $(e.currentTarget);
      $button.prop("disabled", true);

      $.ajax({
        url: chatshopSettings.ajaxUrl,
        type: "POST",
        data: {
          action: "chatshop_sync_data",
          nonce: chatshopSettings.nonce,
        },
        success: (response) => {
          if (response.success) {
            this.showNotice("Data synchronized successfully!", "success");
          } else {
            this.showNotice("Failed to synchronize data.", "error");
          }
        },
        complete: () => {
          $button.prop("disabled", false);
        },
      });
    },

    /**
     * Handle field conditions
     */
    handleFieldConditions(e) {
      const $field = $(e.target);
      const conditions = JSON.parse(
        $field.siblings(".chatshop-field-conditions").text() || "{}"
      );

      Object.keys(conditions).forEach((condition) => {
        const rules = conditions[condition];
        const shouldShow = this.evaluateCondition(rules, $field);

        if (condition === "show_if") {
          $field
            .closest(".chatshop-field-wrapper")
            .toggleClass("chatshop-hidden", !shouldShow);
        } else if (condition === "hide_if") {
          $field
            .closest(".chatshop-field-wrapper")
            .toggleClass("chatshop-hidden", shouldShow);
        }
      });
    },

    /**
     * Evaluate conditional logic
     */
    evaluateCondition(rules, $field) {
      // Simple condition evaluation
      // This would be expanded based on actual requirements
      return true;
    },

    /**
     * Auto-save field
     */
    autoSaveField(e) {
      const $field = $(e.target);
      const fieldName = $field.attr("name");
      const fieldValue = $field.val();

      // Debounce auto-save
      clearTimeout($field.data("autoSaveTimeout"));

      const timeout = setTimeout(() => {
        $.ajax({
          url: chatshopSettings.ajaxUrl,
          type: "POST",
          data: {
            action: "chatshop_auto_save_field",
            field_name: fieldName,
            field_value: fieldValue,
            nonce: chatshopSettings.nonce,
          },
          success: (response) => {
            if (response.success) {
              $field.addClass("auto-saved");
              setTimeout(() => $field.removeClass("auto-saved"), 2000);
            }
          },
        });
      }, 1000);

      $field.data("autoSaveTimeout", timeout);
    },

    /**
     * Parse file size string to bytes
     */
    parseFileSize(sizeStr) {
      const units = {
        B: 1,
        KB: 1024,
        MB: 1024 * 1024,
        GB: 1024 * 1024 * 1024,
      };

      const match = sizeStr.match(/^(\d+(?:\.\d+)?)\s*(B|KB|MB|GB)$/i);
      if (!match) return 0;

      const size = parseFloat(match[1]);
      const unit = match[2].toUpperCase();

      return size * (units[unit] || 1);
    },

    /**
     * Show notification
     */
    showNotice(message, type = "info") {
      const $notice = $("<div>", {
        class: `notice notice-${type} is-dismissible`,
        html: `<p>${message}</p>`,
      });

      $(".chatshop-settings-content").prepend($notice);

      // Auto-dismiss after 5 seconds
      setTimeout(() => {
        $notice.fadeOut(() => $notice.remove());
      }, 5000);
    },

    /**
     * Utility: Debounce function
     */
    debounce(func, delay) {
      let timeoutId;
      return function (...args) {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => func.apply(this, args), delay);
      };
    },
  };

  // Initialize when document is ready
  $(document).ready(() => {
    ChatShopSettings.init();
  });

  // Expose to global scope for external access
  window.ChatShopSettings = ChatShopSettings;
})(jQuery);
