/**
 * ChatShop Admin JavaScript
 *
 * @package    ChatShop
 * @subpackage ChatShop/admin/js
 * @version    1.0.0
 */

(function ($) {
  "use strict";

  /**
   * ChatShop Admin Object
   */
  const ChatShopAdmin = {
    /**
     * Initialize the admin interface
     */
    init: function () {
      this.setupEventHandlers();
      this.initializeComponents();
      this.setupAjaxErrorHandling();
      this.initializeTooltips();
      this.setupKeyboardShortcuts();

      // Page-specific initializations
      this.initializePage();
    },

    /**
     * Setup global event handlers
     */
    setupEventHandlers: function () {
      // Global AJAX loader
      $(document)
        .ajaxStart(function () {
          ChatShopAdmin.showLoader();
        })
        .ajaxStop(function () {
          ChatShopAdmin.hideLoader();
        });

      // Notice dismissal
      $(document).on("click", ".notice-dismiss", function () {
        $(this).closest(".notice").fadeOut();
      });

      // Confirm dangerous actions
      $(document).on("click", "[data-confirm]", function (e) {
        const message = $(this).data("confirm");
        if (!confirm(message)) {
          e.preventDefault();
          return false;
        }
      });

      // Auto-save form data
      $(document).on("change", ".auto-save", function () {
        ChatShopAdmin.autoSaveForm($(this).closest("form"));
      });

      // Copy to clipboard
      $(document).on("click", "[data-copy]", function (e) {
        e.preventDefault();
        const text = $(this).data("copy") || $(this).text();
        ChatShopAdmin.copyToClipboard(text);
        ChatShopAdmin.showNotice(
          chatshop_ajax.strings.copy_success || "Copied to clipboard!",
          "success"
        );
      });

      // Toggle visibility
      $(document).on("click", "[data-toggle]", function (e) {
        e.preventDefault();
        const target = $(this).data("toggle");
        $(target).toggle();
        $(this).toggleClass("active");
      });

      // Modal handling
      $(document).on("click", "[data-modal]", function (e) {
        e.preventDefault();
        const modalId = $(this).data("modal");
        ChatShopAdmin.openModal(modalId);
      });

      $(document).on("click", ".modal-close, .modal-backdrop", function (e) {
        if (e.target === this) {
          ChatShopAdmin.closeModal();
        }
      });

      // Escape key to close modals
      $(document).on("keydown", function (e) {
        if (e.keyCode === 27) {
          // Escape key
          ChatShopAdmin.closeModal();
        }
      });

      // Form validation
      $(document).on("submit", ".validate-form", function (e) {
        if (!ChatShopAdmin.validateForm($(this))) {
          e.preventDefault();
          return false;
        }
      });

      // Dynamic field dependencies
      $(document).on("change", "[data-dependency]", function () {
        ChatShopAdmin.handleFieldDependency($(this));
      });

      // Tab switching
      $(document).on("click", ".nav-tab", function (e) {
        e.preventDefault();
        ChatShopAdmin.switchTab($(this));
      });
    },

    /**
     * Initialize common components
     */
    initializeComponents: function () {
      // Initialize color pickers
      if ($.fn.wpColorPicker) {
        $(".color-picker").wpColorPicker();
      }

      // Initialize sortables
      if ($.fn.sortable) {
        $(".sortable-list").sortable({
          handle: ".sort-handle",
          placeholder: "sort-placeholder",
          update: function (event, ui) {
            ChatShopAdmin.handleSortUpdate($(this));
          },
        });
      }

      // Initialize date pickers
      if ($.fn.datepicker) {
        $(".date-picker").datepicker({
          dateFormat: "yy-mm-dd",
          changeMonth: true,
          changeYear: true,
        });
      }

      // Initialize select2 if available
      if ($.fn.select2) {
        $(".select2").select2({
          width: "100%",
        });
      }

      // Initialize media uploaders
      this.initializeMediaUploaders();

      // Initialize charts if data is available
      this.initializeCharts();

      // Setup real-time updates
      this.setupRealTimeUpdates();
    },

    /**
     * Initialize page-specific functionality
     */
    initializePage: function () {
      const page = chatshop_ajax.current_screen;

      switch (page) {
        case "toplevel_page_chatshop":
          this.initializeDashboard();
          break;
        case "chatshop_page_chatshop-contacts":
          this.initializeContacts();
          break;
        case "chatshop_page_chatshop-campaigns":
          this.initializeCampaigns();
          break;
        case "chatshop_page_chatshop-payment-links":
          this.initializePaymentLinks();
          break;
        case "chatshop_page_chatshop-gateways":
          this.initializeGateways();
          break;
        case "chatshop_page_chatshop-components":
          this.initializeComponents();
          break;
        case "chatshop_page_chatshop-settings":
          this.initializeSettings();
          break;
      }
    },

    /**
     * Initialize dashboard functionality
     */
    initializeDashboard: function () {
      // Refresh dashboard data every 5 minutes
      setInterval(() => {
        this.refreshDashboardStats();
      }, 300000);

      // Setup chart interactions
      this.setupChartInteractions();

      // Initialize quick actions
      this.initializeQuickActions();
    },

    /**
     * Initialize contacts functionality
     */
    initializeContacts: function () {
      // Setup contact management
      this.setupContactActions();
      this.setupContactFilters();
      this.setupBulkActions();
    },

    /**
     * Initialize campaigns functionality
     */
    initializeCampaigns: function () {
      // Setup campaign management
      this.setupCampaignActions();
      this.setupCampaignScheduler();
      this.setupMessagePreview();
    },

    /**
     * Initialize payment links functionality
     */
    initializePaymentLinks: function () {
      // Setup payment link management
      this.setupPaymentLinkGenerator();
      this.setupQRCodeGeneration();
      this.setupLinkTracking();
    },

    /**
     * Initialize gateways functionality
     */
    initializeGateways: function () {
      // Setup gateway management
      this.setupGatewayTesting();
      this.setupWebhookTesting();
      this.setupConnectionTesting();
    },

    /**
     * Initialize settings functionality
     */
    initializeSettings: function () {
      // Setup settings management
      this.setupSettingsValidation();
      this.setupFieldDependencies();
      this.setupLivePreview();
    },

    /**
     * Setup AJAX error handling
     */
    setupAjaxErrorHandling: function () {
      $(document).ajaxError(function (event, xhr, settings, error) {
        console.error("AJAX Error:", error, xhr);

        let message =
          chatshop_ajax.strings.ajax_error ||
          "An error occurred. Please try again.";

        if (xhr.responseJSON && xhr.responseJSON.data) {
          message = xhr.responseJSON.data;
        } else if (xhr.responseText) {
          try {
            const response = JSON.parse(xhr.responseText);
            if (response.data) {
              message = response.data;
            }
          } catch (e) {
            // Use default message
          }
        }

        ChatShopAdmin.showNotice(message, "error");
      });
    },

    /**
     * Initialize tooltips
     */
    initializeTooltips: function () {
      if ($.fn.tooltip) {
        $("[data-tooltip]").tooltip({
          content: function () {
            return $(this).data("tooltip");
          },
          position: {
            my: "center bottom-20",
            at: "center top",
          },
        });
      }
    },

    /**
     * Setup keyboard shortcuts
     */
    setupKeyboardShortcuts: function () {
      $(document).keydown(function (e) {
        // Ctrl/Cmd + S to save
        if ((e.ctrlKey || e.metaKey) && e.keyCode === 83) {
          e.preventDefault();
          const $form = $("form:visible").first();
          if ($form.length) {
            $form.submit();
          }
        }

        // Ctrl/Cmd + Enter to submit forms
        if ((e.ctrlKey || e.metaKey) && e.keyCode === 13) {
          const $form = $("form:visible").first();
          if ($form.length) {
            $form.submit();
          }
        }
      });
    },

    /**
     * Initialize media uploaders
     */
    initializeMediaUploaders: function () {
      $(document).on("click", ".upload-button", function (e) {
        e.preventDefault();

        const $button = $(this);
        const $input = $button.siblings('input[type="text"]');
        const $preview = $button.siblings(".file-preview");

        const frame = wp.media({
          title: $button.data("title") || "Select File",
          button: {
            text: $button.data("button-text") || "Use this file",
          },
          multiple: false,
          library: {
            type: $button.data("media-type") || "image",
          },
        });

        frame.on("select", function () {
          const attachment = frame.state().get("selection").first().toJSON();
          $input.val(attachment.url).trigger("change");

          if ($preview.length) {
            if (attachment.type === "image") {
              $preview.html(
                `<img src="${attachment.url}" style="max-width: 100px; max-height: 100px;">`
              );
            } else {
              $preview.html(
                `<a href="${attachment.url}" target="_blank">${attachment.filename}</a>`
              );
            }
          }
        });

        frame.open();
      });

      $(document).on("click", ".remove-file", function (e) {
        e.preventDefault();

        const $button = $(this);
        const $input = $button.siblings('input[type="text"]');
        const $preview = $button.siblings(".file-preview");

        $input.val("").trigger("change");
        $preview.empty();
      });
    },

    /**
     * Initialize charts
     */
    initializeCharts: function () {
      if (typeof Chart !== "undefined") {
        this.initializeDashboardCharts();
        this.initializeAnalyticsCharts();
      }
    },

    /**
     * Initialize dashboard charts
     */
    initializeDashboardCharts: function () {
      // Revenue chart
      const revenueCanvas = document.getElementById("revenue-chart");
      if (revenueCanvas && window.chartData) {
        new Chart(revenueCanvas.getContext("2d"), {
          type: "line",
          data: {
            labels: window.chartData.labels,
            datasets: [
              {
                label: "Revenue",
                data: window.chartData.revenue,
                borderColor: "#2196F3",
                backgroundColor: "rgba(33, 150, 243, 0.1)",
                tension: 0.4,
                fill: true,
              },
            ],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
              y: {
                beginAtZero: true,
                ticks: {
                  callback: function (value) {
                    return new Intl.NumberFormat("en-US", {
                      style: "currency",
                      currency: "USD",
                    }).format(value);
                  },
                },
              },
            },
            plugins: {
              legend: {
                display: false,
              },
              tooltip: {
                mode: "index",
                intersect: false,
              },
            },
          },
        });
      }

      // Contacts chart
      const contactsCanvas = document.getElementById("contacts-chart");
      if (contactsCanvas && window.chartData) {
        new Chart(contactsCanvas.getContext("2d"), {
          type: "line",
          data: {
            labels: window.chartData.labels,
            datasets: [
              {
                label: "New Contacts",
                data: window.chartData.contacts,
                borderColor: "#4CAF50",
                backgroundColor: "rgba(76, 175, 80, 0.1)",
                tension: 0.4,
                fill: true,
              },
            ],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
              y: {
                beginAtZero: true,
              },
            },
            plugins: {
              legend: {
                display: false,
              },
              tooltip: {
                mode: "index",
                intersect: false,
              },
            },
          },
        });
      }
    },

    /**
     * Setup real-time updates
     */
    setupRealTimeUpdates: function () {
      // Update stats every 30 seconds
      setInterval(() => {
        this.updateLiveStats();
      }, 30000);
    },

    /**
     * Update live statistics
     */
    updateLiveStats: function () {
      $.post(
        chatshop_ajax.ajax_url,
        {
          action: "chatshop_ajax",
          chatshop_action: "get_live_stats",
          nonce: chatshop_ajax.nonce,
        },
        (response) => {
          if (response.success) {
            this.updateStatsDisplay(response.data);
          }
        }
      );
    },

    /**
     * Update stats display
     */
    updateStatsDisplay: function (stats) {
      Object.keys(stats).forEach((key) => {
        const $element = $(`.stat-${key} .stat-number, .${key}-count`);
        if ($element.length) {
          this.animateNumber($element, stats[key]);
        }
      });
    },

    /**
     * Animate number changes
     */
    animateNumber: function ($element, newValue) {
      const currentValue =
        parseInt($element.text().replace(/[^0-9]/g, "")) || 0;

      $({ value: currentValue }).animate(
        { value: newValue },
        {
          duration: 1000,
          easing: "swing",
          step: function () {
            $element.text(Math.ceil(this.value).toLocaleString());
          },
        }
      );
    },

    /**
     * Show loading overlay
     */
    showLoader: function (target = "body") {
      const $target = $(target);

      if (!$target.find(".chatshop-loader").length) {
        const $loader = $(`
                    <div class="chatshop-loader">
                        <div class="loader-spinner">
                            <div class="spinner is-active"></div>
                            <p>${chatshop_ajax.strings.loading}</p>
                        </div>
                    </div>
                `);

        $target.append($loader);
      }
    },

    /**
     * Hide loading overlay
     */
    hideLoader: function (target = "body") {
      $(target).find(".chatshop-loader").remove();
    },

    /**
     * Show notification
     */
    showNotice: function (message, type = "info", dismissible = true) {
      const $notice = $(`
                <div class="notice notice-${type} ${
        dismissible ? "is-dismissible" : ""
      }">
                    <p>${message}</p>
                    ${
                      dismissible
                        ? '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>'
                        : ""
                    }
                </div>
            `);

      $(".wrap h1").first().after($notice);

      // Auto-dismiss after 5 seconds for success messages
      if (type === "success" && dismissible) {
        setTimeout(() => {
          $notice.fadeOut(() => $notice.remove());
        }, 5000);
      }

      // Scroll to notice
      $("html, body").animate(
        {
          scrollTop: $notice.offset().top - 100,
        },
        300
      );
    },

    /**
     * Copy text to clipboard
     */
    copyToClipboard: function (text) {
      if (navigator.clipboard) {
        navigator.clipboard.writeText(text);
      } else {
        // Fallback for older browsers
        const $temp = $("<textarea>");
        $("body").append($temp);
        $temp.val(text).select();
        document.execCommand("copy");
        $temp.remove();
      }
    },

    /**
     * Open modal
     */
    openModal: function (modalId) {
      const $modal = $(`#${modalId}`);
      if ($modal.length) {
        $modal.removeClass("hidden").addClass("active");
        $("body").addClass("modal-open");

        // Focus first input
        $modal.find("input, textarea, select").first().focus();
      }
    },

    /**
     * Close modal
     */
    closeModal: function () {
      $(".chatshop-modal.active").addClass("hidden").removeClass("active");
      $("body").removeClass("modal-open");
    },

    /**
     * Validate form
     */
    validateForm: function ($form) {
      let isValid = true;
      const errors = [];

      // Remove previous error styling
      $form.find(".error").removeClass("error");
      $form.find(".validation-error").remove();

      // Check required fields
      $form.find("[required]").each(function () {
        const $field = $(this);
        const value = $field.val();

        if (!value || (Array.isArray(value) && value.length === 0)) {
          isValid = false;
          $field.addClass("error");

          const fieldName = $field.attr("name") || $field.attr("id") || "Field";
          errors.push(`${fieldName} is required`);

          $field.after(
            '<span class="validation-error">This field is required</span>'
          );
        }
      });

      // Check email fields
      $form.find('input[type="email"]').each(function () {
        const $field = $(this);
        const value = $field.val();

        if (value && !ChatShopAdmin.isValidEmail(value)) {
          isValid = false;
          $field.addClass("error");
          errors.push("Invalid email format");

          $field.after(
            '<span class="validation-error">Please enter a valid email</span>'
          );
        }
      });

      // Check URL fields
      $form.find('input[type="url"]').each(function () {
        const $field = $(this);
        const value = $field.val();

        if (value && !ChatShopAdmin.isValidUrl(value)) {
          isValid = false;
          $field.addClass("error");
          errors.push("Invalid URL format");

          $field.after(
            '<span class="validation-error">Please enter a valid URL</span>'
          );
        }
      });

      // Custom validation rules
      $form.find("[data-validate]").each(function () {
        const $field = $(this);
        const rule = $field.data("validate");
        const value = $field.val();

        if (!ChatShopAdmin.customValidation(rule, value)) {
          isValid = false;
          $field.addClass("error");

          const message = $field.data("validate-message") || "Invalid value";
          $field.after(`<span class="validation-error">${message}</span>`);
        }
      });

      if (!isValid) {
        // Scroll to first error
        const $firstError = $form.find(".error").first();
        if ($firstError.length) {
          $("html, body").animate(
            {
              scrollTop: $firstError.offset().top - 100,
            },
            300
          );
        }
      }

      return isValid;
    },

    /**
     * Custom validation rules
     */
    customValidation: function (rule, value) {
      switch (rule) {
        case "phone":
          return /^[\+]?[1-9][\d]{0,15}$/.test(value);
        case "api_key":
          return value.length >= 10;
        case "webhook_url":
          return ChatShopAdmin.isValidUrl(value) && value.includes("webhook");
        default:
          return true;
      }
    },

    /**
     * Validate email
     */
    isValidEmail: function (email) {
      const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      return regex.test(email);
    },

    /**
     * Validate URL
     */
    isValidUrl: function (url) {
      try {
        new URL(url);
        return true;
      } catch {
        return false;
      }
    },

    /**
     * Handle field dependencies
     */
    handleFieldDependency: function ($field) {
      const dependency = $field.data("dependency");
      const value = $field.val();
      const checked = $field.is(":checked");

      if (dependency) {
        const $dependent = $(dependency);

        if ($field.is(":checkbox")) {
          if (checked) {
            $dependent.show().prop("disabled", false);
          } else {
            $dependent.hide().prop("disabled", true);
          }
        } else {
          const showValues = $field.data("show-values");
          if (showValues) {
            const values = showValues.split(",");
            if (values.includes(value)) {
              $dependent.show().prop("disabled", false);
            } else {
              $dependent.hide().prop("disabled", true);
            }
          }
        }
      }
    },

    /**
     * Switch tabs
     */
    switchTab: function ($tab) {
      const targetTab = $tab.attr("href").replace("#", "");

      // Update tab state
      $tab.siblings().removeClass("nav-tab-active");
      $tab.addClass("nav-tab-active");

      // Show/hide content
      $(".tab-content").hide();
      $(`#${targetTab}`).show();

      // Update URL without reload
      if (history.pushState) {
        const url = new URL(window.location);
        url.searchParams.set("tab", targetTab);
        history.pushState({}, "", url);
      }
    },

    /**
     * Auto-save form data
     */
    autoSaveForm: function ($form) {
      const formData = $form.serialize();
      const formId = $form.attr("id");

      if (formId) {
        localStorage.setItem(`chatshop_autosave_${formId}`, formData);

        // Show auto-save indicator
        this.showAutoSaveIndicator($form);
      }
    },

    /**
     * Show auto-save indicator
     */
    showAutoSaveIndicator: function ($form) {
      let $indicator = $form.find(".autosave-indicator");

      if (!$indicator.length) {
        $indicator = $('<span class="autosave-indicator">Draft saved</span>');
        $form.find(".submit").append($indicator);
      }

      $indicator.show().delay(2000).fadeOut();
    },

    /**
     * Load auto-saved data
     */
    loadAutoSavedData: function ($form) {
      const formId = $form.attr("id");

      if (formId) {
        const savedData = localStorage.getItem(`chatshop_autosave_${formId}`);

        if (savedData) {
          // Parse and populate form
          const data = new URLSearchParams(savedData);

          data.forEach((value, name) => {
            const $field = $form.find(`[name="${name}"]`);

            if ($field.is(":checkbox") || $field.is(":radio")) {
              $field.filter(`[value="${value}"]`).prop("checked", true);
            } else {
              $field.val(value);
            }
          });

          this.showNotice("Auto-saved data restored", "info");
        }
      }
    },

    /**
     * Clear auto-saved data
     */
    clearAutoSavedData: function ($form) {
      const formId = $form.attr("id");

      if (formId) {
        localStorage.removeItem(`chatshop_autosave_${formId}`);
      }
    },

    /**
     * Setup contact actions
     */
    setupContactActions: function () {
      // Send message action
      $(document).on("click", ".send-message", function (e) {
        e.preventDefault();
        const contactId = $(this).data("contact-id");
        ChatShopAdmin.openSendMessageModal(contactId);
      });

      // Edit contact action
      $(document).on("click", ".edit-contact", function (e) {
        e.preventDefault();
        const contactId = $(this).data("contact-id");
        window.location.href = `admin.php?page=chatshop-contacts&action=edit&contact_id=${contactId}`;
      });

      // Delete contact action
      $(document).on("click", ".delete-contact", function (e) {
        e.preventDefault();
        if (confirm("Are you sure you want to delete this contact?")) {
          const contactId = $(this).data("contact-id");
          ChatShopAdmin.deleteContact(contactId);
        }
      });
    },

    /**
     * Open send message modal
     */
    openSendMessageModal: function (contactId) {
      // Load contact details and show modal
      $.post(
        chatshop_ajax.ajax_url,
        {
          action: "chatshop_ajax",
          chatshop_action: "get_contact_details",
          contact_id: contactId,
          nonce: chatshop_ajax.nonce,
        },
        (response) => {
          if (response.success) {
            const contact = response.data;
            $("#send-message-modal #recipient-name").val(contact.name);
            $("#send-message-modal #recipient-phone").val(contact.phone);
            this.openModal("send-message-modal");
          }
        }
      );
    },

    /**
     * Setup payment link generator
     */
    setupPaymentLinkGenerator: function () {
      $(document).on("click", ".generate-payment-link", function (e) {
        e.preventDefault();
        const $button = $(this);
        const originalText = $button.text();

        $button.prop("disabled", true).text("Generating...");

        const linkData = {
          amount: $("#payment-amount").val(),
          currency: $("#payment-currency").val(),
          description: $("#payment-description").val(),
          customer_email: $("#customer-email").val(),
        };

        $.post(
          chatshop_ajax.ajax_url,
          {
            action: "chatshop_ajax",
            chatshop_action: "generate_payment_link",
            link_data: linkData,
            nonce: chatshop_ajax.nonce,
          },
          (response) => {
            $button.prop("disabled", false).text(originalText);

            if (response.success) {
              $("#generated-link").val(response.data.link);
              $("#qr-code").html(response.data.qr_code);
              $(".payment-link-result").show();

              ChatShopAdmin.showNotice(
                "Payment link generated successfully!",
                "success"
              );
            } else {
              ChatShopAdmin.showNotice(response.data, "error");
            }
          }
        );
      });
    },

    /**
     * Setup QR code generation
     */
    setupQRCodeGeneration: function () {
      if (typeof QRCode !== "undefined") {
        $(document).on("input", "#generated-link", function () {
          const link = $(this).val();
          if (link) {
            const qr = new QRCode(document.getElementById("qr-code"), {
              text: link,
              width: 200,
              height: 200,
            });
          }
        });
      }
    },

    /**
     * Setup gateway testing
     */
    setupGatewayTesting: function () {
      $(document).on("click", ".test-gateway", function (e) {
        e.preventDefault();
        const gateway = $(this).data("gateway");
        ChatShopAdmin.testGateway(gateway);
      });
    },

    /**
     * Test gateway connection
     */
    testGateway: function (gateway) {
      const $button = $(`.test-gateway[data-gateway="${gateway}"]`);
      const originalText = $button.text();

      $button.prop("disabled", true).text("Testing...");

      $.post(
        chatshop_ajax.ajax_url,
        {
          action: "chatshop_ajax",
          chatshop_action: "test_gateway",
          gateway: gateway,
          nonce: chatshop_ajax.nonce,
        },
        (response) => {
          $button.prop("disabled", false).text(originalText);

          if (response.success) {
            ChatShopAdmin.showNotice(response.data, "success");
          } else {
            ChatShopAdmin.showNotice(response.data, "error");
          }
        }
      );
    },

    /**
     * Refresh dashboard stats
     */
    refreshDashboardStats: function () {
      $.post(
        chatshop_ajax.ajax_url,
        {
          action: "chatshop_ajax",
          chatshop_action: "refresh_dashboard_stats",
          nonce: chatshop_ajax.nonce,
        },
        (response) => {
          if (response.success) {
            this.updateStatsDisplay(response.data);
          }
        }
      );
    },

    /**
     * Handle sort update
     */
    handleSortUpdate: function ($list) {
      const items = $list.sortable("toArray", { attribute: "data-id" });

      $.post(
        chatshop_ajax.ajax_url,
        {
          action: "chatshop_ajax",
          chatshop_action: "update_sort_order",
          items: items,
          nonce: chatshop_ajax.nonce,
        },
        (response) => {
          if (response.success) {
            ChatShopAdmin.showNotice("Order updated successfully!", "success");
          } else {
            ChatShopAdmin.showNotice("Failed to update order", "error");
          }
        }
      );
    },

    /**
     * Setup bulk actions
     */
    setupBulkActions: function () {
      $(document).on("click", "#doaction", function (e) {
        const action = $("#bulk-action-selector-top").val();
        const selected = $('input[name="contact_ids[]"]:checked')
          .map(function () {
            return this.value;
          })
          .get();

        if (action === "-1" || selected.length === 0) {
          e.preventDefault();
          alert("Please select an action and at least one item.");
          return;
        }

        if (action === "delete") {
          if (
            !confirm(
              `Are you sure you want to delete ${selected.length} items?`
            )
          ) {
            e.preventDefault();
            return;
          }
        }
      });
    },

    /**
     * Utility function to debounce function calls
     */
    debounce: function (func, wait, immediate) {
      let timeout;
      return function executedFunction() {
        const context = this;
        const args = arguments;

        const later = function () {
          timeout = null;
          if (!immediate) func.apply(context, args);
        };

        const callNow = immediate && !timeout;
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);

        if (callNow) func.apply(context, args);
      };
    },

    /**
     * Utility function to throttle function calls
     */
    throttle: function (func, limit) {
      let inThrottle;
      return function () {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
          func.apply(context, args);
          inThrottle = true;
          setTimeout(() => (inThrottle = false), limit);
        }
      };
    },
  };

  /**
   * Initialize when document is ready
   */
  $(document).ready(function () {
    ChatShopAdmin.init();
  });

  /**
   * Expose ChatShopAdmin globally
   */
  window.ChatShopAdmin = ChatShopAdmin;
})(jQuery);
