/**
 * Woo2Shopify Admin JavaScript
 */

(function($) {
    'use strict';
    
    var Woo2Shopify = {
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initRangeSliders();
            this.initTabs();
        },

        initTabs: function() {
            // Initialize selective migration tab
            if ($('#products-list').length) {
                this.loadProducts({});
            }

            // Initialize page migration tab
            if ($('#pages-list').length) {
                this.loadPages({});
            }
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            $('#test-connection').on('click', this.testConnection);
            $('#start-migration').on('click', this.startMigration);
            $('#stop-migration').on('click', this.stopMigration);
            $('#clear-logs').on('click', this.clearLogs);
            $('#clear-video-cache').on('click', this.clearVideoCache);

            // Selective migration events
            $('#filter-products').on('click', this.filterProducts);
            $('#select-all-products').on('click', this.selectAllProducts);
            $('#deselect-all-products').on('click', this.deselectAllProducts);
            $('#migrate-selected-products').on('click', this.migrateSelectedProducts);
            $('#load-more-products').on('click', this.loadMoreProducts);

            // Page migration events
            $('#filter-pages').on('click', this.filterPages);
            $('#select-all-pages').on('click', this.selectAllPages);
            $('#deselect-all-pages').on('click', this.deselectAllPages);
            $('#migrate-selected-pages').on('click', this.migrateSelectedPages);
            $('#load-more-pages').on('click', this.loadMorePages);

            // Product selection change
            $(document).on('change', '.product-checkbox', this.updateSelectedCount);
            $(document).on('change', '.page-checkbox', this.updateSelectedPagesCount);
        },
        
        /**
         * Initialize range sliders
         */
        initRangeSliders: function() {
            $('.woo2shopify-range').on('input', function() {
                $(this).next('.range-value').text($(this).val() + '%');
            });
        },
        
        /**
         * Test Shopify connection
         */
        testConnection: function(e) {
            e.preventDefault();

            var $button = $(this);
            var $result = $('#connection-result');

            // Show loading state
            $button.addClass('loading').prop('disabled', true).text('Testing...');
            $result.hide().removeClass('success error');

            console.log('Woo2Shopify: Starting connection test...');

            $.ajax({
                url: woo2shopify_ajax.ajax_url,
                type: 'POST',
                timeout: 30000, // 30 seconds timeout
                data: {
                    action: 'woo2shopify_test_connection',
                    nonce: woo2shopify_ajax.nonce
                },
                success: function(response) {
                    console.log('Woo2Shopify: AJAX response received', response);

                    if (response.success) {
                        var successMessage = '<strong>' + woo2shopify_ajax.strings.connection_successful + '</strong><br>' +
                            (response.data.message || '');

                        if (response.data.auth_method) {
                            successMessage += '<br><small>Authentication: ' + response.data.auth_method + '</small>';
                        }

                        $result.addClass('success').html(successMessage).show();
                    } else {
                        var errorMessage = '<strong>' + woo2shopify_ajax.strings.connection_failed + '</strong><br>' +
                            (response.data.message || response.data || 'Unknown error');

                        // Add debug info if available
                        if (response.data.debug_info) {
                            errorMessage += '<br><small>Debug: ' + JSON.stringify(response.data.debug_info) + '</small>';
                        }

                        $result.addClass('error').html(errorMessage).show();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Woo2Shopify: AJAX error', {xhr: xhr, status: status, error: error});

                    var errorMessage = '<strong>' + woo2shopify_ajax.strings.connection_failed + '</strong><br>';

                    if (status === 'timeout') {
                        errorMessage += 'Request timed out. Please check your Shopify credentials and try again.';
                    } else if (xhr.status === 0) {
                        errorMessage += 'Network error. Please check your internet connection.';
                    } else if (xhr.status >= 500) {
                        errorMessage += 'Server error (' + xhr.status + '). Please try again later.';
                    } else {
                        errorMessage += error + ' (Status: ' + xhr.status + ')';
                    }

                    $result.addClass('error').html(errorMessage).show();
                },
                complete: function() {
                    $button.removeClass('loading').prop('disabled', false).text('Test Shopify Connection');
                    console.log('Woo2Shopify: Connection test completed');
                }
            });
        },
        
        /**
         * Start migration
         */
        startMigration: function(e) {
            e.preventDefault();
            
            if (!confirm(woo2shopify_ajax.strings.confirm_migration)) {
                return;
            }
            
            var $button = $(this);
            var $stopButton = $('#stop-migration');
            var $progress = $('#migration-progress');
            var $results = $('#migration-results');
            
            // Reset UI
            $results.hide();
            $progress.show();
            $button.hide();
            $stopButton.show();
            
            // Reset progress
            Woo2Shopify.updateProgress(0, 0, 0, woo2shopify_ajax.strings.migration_started);
            
            // Start migration
            $.ajax({
                url: woo2shopify_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'woo2shopify_start_migration',
                    nonce: woo2shopify_ajax.nonce,
                    include_images: $('#include-images').is(':checked'),
                    include_videos: $('#include-videos').is(':checked'),
                    include_variations: $('#include-variations').is(':checked'),
                    include_categories: $('#include-categories').is(':checked'),
                    include_translations: $('#include-translations').is(':checked')
                },
                success: function(response) {
                    console.log('Start Migration Response:', response);

                    if (response.success) {
                        if (response.data && response.data.migration_id) {
                            // Store migration ID for stop functionality
                            Woo2Shopify.currentMigrationId = response.data.migration_id;
                            Woo2Shopify.trackProgress(response.data.migration_id);
                        } else {
                            console.error('Missing migration_id in response:', response);
                            Woo2Shopify.showError('Migration started but no ID returned. Check logs.');
                        }
                    } else {
                        var errorMsg = 'Failed to start migration';
                        if (response.data && response.data.message) {
                            errorMsg = response.data.message;
                        } else if (response.message) {
                            errorMsg = response.message;
                        }
                        console.error('Migration start failed:', response);
                        Woo2Shopify.showError(errorMsg);
                    }
                },
                error: function(xhr, status, error) {
                    Woo2Shopify.showError(error);
                }
            });
        },
        
        /**
         * Stop migration
         */
        stopMigration: function(e) {
            e.preventDefault();

            if (!confirm('Are you sure you want to stop the migration?')) {
                return;
            }

            // Stop progress tracking
            if (Woo2Shopify.progressInterval) {
                clearInterval(Woo2Shopify.progressInterval);
            }

            // Get current migration ID
            var migrationId = Woo2Shopify.currentMigrationId || '';

            // Send stop request to backend
            jQuery.post(woo2shopify_ajax.ajax_url, {
                action: 'woo2shopify_stop_migration',
                nonce: woo2shopify_ajax.nonce,
                migration_id: migrationId
            }, function(response) {
                console.log('Stop Migration Response:', response);

                $('#start-migration').show();
                $('#stop-migration').hide();
                $('#current-status').text('Migration stopped by user');

                if (response.success) {
                    Woo2Shopify.showSuccess('Migration stopped successfully');
                } else {
                    Woo2Shopify.showError('Failed to stop migration: ' + (response.message || 'Unknown error'));
                }
            }).fail(function() {
                $('#start-migration').show();
                $('#stop-migration').hide();
                $('#current-status').text('Migration stopped (connection failed)');
                Woo2Shopify.showError('Failed to communicate with server');
            });
        },
        
        /**
         * Track migration progress
         */
        trackProgress: function(migrationId) {
            Woo2Shopify.progressInterval = setInterval(function() {
                $.ajax({
                    url: woo2shopify_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'woo2shopify_get_progress',
                        nonce: woo2shopify_ajax.nonce,
                        migration_id: migrationId
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            var data = response.data;
                            var percentage = data.total_products > 0 ? 
                                Math.round((data.processed_products / data.total_products) * 100) : 0;
                            
                            Woo2Shopify.updateProgress(
                                percentage,
                                data.processed_products,
                                data.total_products,
                                data.status_message || 'Processing...'
                            );
                            
                            // Check if completed
                            if (data.status === 'completed' || data.status === 'failed') {
                                clearInterval(Woo2Shopify.progressInterval);
                                Woo2Shopify.showResults(data);
                            }
                        }
                    },
                    error: function() {
                        clearInterval(Woo2Shopify.progressInterval);
                        Woo2Shopify.showError('Failed to get progress update');
                    }
                });
            }, 2000); // Check every 2 seconds
        },
        
        /**
         * Update progress display
         */
        updateProgress: function(percentage, processed, total, status) {
            $('.progress-fill').css('width', percentage + '%');
            $('.progress-text').text(percentage + '%');
            $('#processed-count').text(processed);
            $('#total-count').text(total);
            $('#current-status').text(status);
        },
        
        /**
         * Show migration results
         */
        showResults: function(data) {
            $('#migration-progress').hide();
            $('#start-migration').show();
            $('#stop-migration').hide();
            
            $('#success-count').text(data.successful_products || 0);
            $('#failed-count').text(data.failed_products || 0);
            $('#migration-results').show();
            
            // Show completion message
            var message = data.status === 'completed' ? 
                woo2shopify_ajax.strings.migration_completed :
                woo2shopify_ajax.strings.migration_failed;
            
            Woo2Shopify.showNotice(message, data.status === 'completed' ? 'success' : 'error');
        },
        
        /**
         * Show error message
         */
        showError: function(message) {
            $('#migration-progress').hide();
            $('#start-migration').show();
            $('#stop-migration').hide();
            
            Woo2Shopify.showNotice(message, 'error');
        },
        
        /**
         * Show notice
         */
        showNotice: function(message, type) {
            var $notice = $('<div class="woo2shopify-notice ' + type + '">' + message + '</div>');
            $('.woo2shopify-dashboard').prepend($notice);
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },
        
        /**
         * Clear logs
         */
        clearLogs: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to clear all logs? This action cannot be undone.')) {
                return;
            }
            
            var $button = $(this);
            $button.addClass('loading').prop('disabled', true);
            
            $.ajax({
                url: woo2shopify_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'woo2shopify_clear_logs',
                    nonce: woo2shopify_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Failed to clear logs: ' + (response.data || 'Unknown error'));
                    }
                },
                error: function(xhr, status, error) {
                    alert('Failed to clear logs: ' + error);
                },
                complete: function() {
                    $button.removeClass('loading').prop('disabled', false);
                }
            });
        },

        /**
         * Clear video cache
         */
        clearVideoCache: function(e) {
            e.preventDefault();

            var $button = $(this);

            // Confirm action
            if (!confirm('Are you sure you want to clear the video cache? This will remove all cached video information.')) {
                return;
            }

            // Show loading state
            $button.addClass('loading').prop('disabled', true).text('Clearing...');

            console.log('Woo2Shopify: Clearing video cache...');

            // Make AJAX request
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'woo2shopify_clear_video_cache',
                    nonce: woo2shopify_admin.nonce
                },
                success: function(response) {
                    console.log('Woo2Shopify: Video cache clear response:', response);

                    if (response.success) {
                        // Show success message
                        $('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>')
                            .insertAfter('.wrap h1')
                            .delay(3000)
                            .fadeOut();

                        // Refresh the page to update stats
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        alert('Error: ' + (response.data.message || 'Unknown error occurred'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Woo2Shopify: Video cache clear error:', error);
                    alert('Error clearing video cache: ' + error);
                },
                complete: function() {
                    // Reset button state
                    $button.removeClass('loading').prop('disabled', false).text('Clear Video Cache');
                }
            });
        },

        /**
         * Filter products
         */
        filterProducts: function(e) {
            e.preventDefault();

            var search = $('#product-search').val();
            var category = $('#product-category').val();
            var status = $('#product-status').val();

            Woo2Shopify.loadProducts({
                search: search,
                category: category,
                status: status,
                offset: 0
            });
        },

        /**
         * Load products
         */
        loadProducts: function(params) {
            var defaults = {
                limit: 50,
                offset: 0,
                search: '',
                category: '',
                status: 'any'
            };

            params = $.extend(defaults, params);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'woo2shopify_get_products_for_selection',
                    nonce: woo2shopify_admin.nonce,
                    limit: params.limit,
                    offset: params.offset,
                    search: params.search,
                    category: params.category,
                    status: params.status
                },
                success: function(response) {
                    if (response.success) {
                        if (params.offset === 0) {
                            $('#products-list').empty();
                        }
                        Woo2Shopify.renderProducts(response.data.products);

                        if (!response.data.has_more) {
                            $('#load-more-products').hide();
                        } else {
                            $('#load-more-products').show().data('offset', params.offset + params.limit);
                        }
                    } else {
                        alert('Error loading products: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('Error loading products');
                }
            });
        },

        /**
         * Render products
         */
        renderProducts: function(products) {
            var html = '';

            products.forEach(function(product) {
                var imageHtml = product.image ?
                    '<img src="' + product.image + '" alt="' + product.title + '">' :
                    '<div class="no-image">No Image</div>';

                var statusClass = product.migrated ? 'migrated' : '';
                var statusText = product.migrated ? 'Migrated' : 'Not Migrated';

                html += '<div class="product-item ' + statusClass + '">';
                html += '<label>';
                html += '<input type="checkbox" class="product-checkbox" value="' + product.id + '"' +
                        (product.migrated ? ' disabled' : '') + '>';
                html += '<div class="product-info">';
                html += '<div class="product-image">' + imageHtml + '</div>';
                html += '<div class="product-details">';
                html += '<h4>' + product.title + '</h4>';
                html += '<p>SKU: ' + (product.sku || 'N/A') + '</p>';
                html += '<p>Price: $' + (product.price || '0') + '</p>';
                html += '<p>Status: ' + statusText + '</p>';
                html += '<p>Categories: ' + (product.categories.join(', ') || 'None') + '</p>';
                html += '</div>';
                html += '</div>';
                html += '</label>';
                html += '</div>';
            });

            $('#products-list').append(html);
        },

        /**
         * Load more products
         */
        loadMoreProducts: function(e) {
            e.preventDefault();

            var offset = $(this).data('offset') || 0;
            var search = $('#product-search').val();
            var category = $('#product-category').val();
            var status = $('#product-status').val();

            Woo2Shopify.loadProducts({
                search: search,
                category: category,
                status: status,
                offset: offset
            });
        },

        /**
         * Select all products
         */
        selectAllProducts: function(e) {
            e.preventDefault();
            $('.product-checkbox:not(:disabled)').prop('checked', true);
            Woo2Shopify.updateSelectedCount();
        },

        /**
         * Deselect all products
         */
        deselectAllProducts: function(e) {
            e.preventDefault();
            $('.product-checkbox').prop('checked', false);
            Woo2Shopify.updateSelectedCount();
        },

        /**
         * Update selected count
         */
        updateSelectedCount: function() {
            var count = $('.product-checkbox:checked').length;
            $('#selected-count').text(count + ' products selected');
            $('#migrate-selected-products').prop('disabled', count === 0);
        },

        /**
         * Migrate selected products
         */
        migrateSelectedProducts: function(e) {
            e.preventDefault();

            var selectedIds = [];
            $('.product-checkbox:checked').each(function() {
                selectedIds.push($(this).val());
            });

            if (selectedIds.length === 0) {
                alert('Please select products to migrate');
                return;
            }

            if (!confirm('Are you sure you want to migrate ' + selectedIds.length + ' products?')) {
                return;
            }

            var $button = $(this);
            $button.prop('disabled', true).text('Migrating...');

            $('#selective-migration-progress').show();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'woo2shopify_migrate_selected_products',
                    nonce: woo2shopify_admin.nonce,
                    product_ids: selectedIds,
                    include_images: $('#selective-include-images').is(':checked'),
                    include_videos: $('#selective-include-videos').is(':checked'),
                    include_variations: $('#selective-include-variations').is(':checked'),
                    include_categories: $('#selective-include-categories').is(':checked')
                },
                success: function(response) {
                    if (response.success) {
                        Woo2Shopify.showMigrationResults(response.data.results);
                        // Refresh product list
                        Woo2Shopify.filterProducts({preventDefault: function(){}});
                    } else {
                        alert('Migration failed: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('Migration failed due to server error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Migrate Selected');
                    $('#selective-migration-progress').hide();
                }
            });
        },

        /**
         * Page migration functions
         */
        filterPages: function(e) {
            e.preventDefault();

            var search = $('#page-search').val();
            var status = $('#page-status').val();

            Woo2Shopify.loadPages({
                search: search,
                status: status,
                offset: 0
            });
        },

        loadPages: function(params) {
            var defaults = {
                limit: 50,
                offset: 0,
                search: '',
                status: 'publish'
            };

            params = $.extend(defaults, params);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'woo2shopify_get_pages_for_selection',
                    nonce: woo2shopify_admin.nonce,
                    limit: params.limit,
                    offset: params.offset,
                    search: params.search,
                    status: params.status
                },
                success: function(response) {
                    if (response.success) {
                        if (params.offset === 0) {
                            $('#pages-list').empty();
                        }
                        Woo2Shopify.renderPages(response.data.pages);

                        if (!response.data.has_more) {
                            $('#load-more-pages').hide();
                        } else {
                            $('#load-more-pages').show().data('offset', params.offset + params.limit);
                        }
                    } else {
                        alert('Error loading pages: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('Error loading pages');
                }
            });
        },

        renderPages: function(pages) {
            var html = '';

            pages.forEach(function(page) {
                var statusClass = page.migrated ? 'migrated' : '';
                var statusText = page.migrated ? 'Migrated' : 'Not Migrated';

                html += '<div class="page-item ' + statusClass + '">';
                html += '<label>';
                html += '<input type="checkbox" class="page-checkbox" value="' + page.id + '"' +
                        (page.migrated ? ' disabled' : '') + '>';
                html += '<div class="page-info">';
                html += '<h4>' + page.title + '</h4>';
                html += '<p>Slug: ' + page.slug + '</p>';
                html += '<p>Content Length: ' + page.content_length + ' chars</p>';
                html += '<p>Status: ' + statusText + '</p>';
                html += '<p>Modified: ' + page.date_modified + '</p>';
                html += '</div>';
                html += '</label>';
                html += '</div>';
            });

            $('#pages-list').append(html);
        },

        loadMorePages: function(e) {
            e.preventDefault();

            var offset = $(this).data('offset') || 0;
            var search = $('#page-search').val();
            var status = $('#page-status').val();

            Woo2Shopify.loadPages({
                search: search,
                status: status,
                offset: offset
            });
        },

        selectAllPages: function(e) {
            e.preventDefault();
            $('.page-checkbox:not(:disabled)').prop('checked', true);
            Woo2Shopify.updateSelectedPagesCount();
        },

        deselectAllPages: function(e) {
            e.preventDefault();
            $('.page-checkbox').prop('checked', false);
            Woo2Shopify.updateSelectedPagesCount();
        },

        updateSelectedPagesCount: function() {
            var count = $('.page-checkbox:checked').length;
            $('#selected-pages-count').text(count + ' pages selected');
            $('#migrate-selected-pages').prop('disabled', count === 0);
        },

        migrateSelectedPages: function(e) {
            e.preventDefault();

            var selectedIds = [];
            $('.page-checkbox:checked').each(function() {
                selectedIds.push($(this).val());
            });

            if (selectedIds.length === 0) {
                alert('Please select pages to migrate');
                return;
            }

            if (!confirm('Are you sure you want to migrate ' + selectedIds.length + ' pages?')) {
                return;
            }

            var $button = $(this);
            $button.prop('disabled', true).text('Migrating...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'woo2shopify_migrate_selected_pages',
                    nonce: woo2shopify_admin.nonce,
                    page_ids: selectedIds
                },
                success: function(response) {
                    if (response.success) {
                        Woo2Shopify.showPageMigrationResults(response.data);
                        // Refresh page list
                        Woo2Shopify.filterPages({preventDefault: function(){}});
                    } else {
                        alert('Page migration failed: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('Page migration failed due to server error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Migrate Selected');
                }
            });
        },

        showMigrationResults: function(results) {
            var html = '<h4>Migration Results</h4>';
            html += '<p>Successful: ' + results.successful + '</p>';
            html += '<p>Failed: ' + results.failed + '</p>';

            if (results.errors.length > 0) {
                html += '<h5>Errors:</h5><ul>';
                results.errors.forEach(function(error) {
                    html += '<li>Product ID ' + error.product_id + ': ' + error.error + '</li>';
                });
                html += '</ul>';
            }

            $('<div class="notice notice-info"><p>' + html + '</p></div>')
                .insertAfter('.wrap h1')
                .delay(5000)
                .fadeOut();
        },

        showPageMigrationResults: function(results) {
            var html = '<h4>Page Migration Results</h4>';
            html += '<p>Successful: ' + results.successful + '</p>';
            html += '<p>Failed: ' + results.failed + '</p>';

            if (results.errors.length > 0) {
                html += '<h5>Errors:</h5><ul>';
                results.errors.forEach(function(error) {
                    html += '<li>Page ID ' + error.page_id + ': ' + error.error + '</li>';
                });
                html += '</ul>';
            }

            $('#page-migration-results .results-summary').html(html);
            $('#page-migration-results').show();
        },

        /**
         * Enhanced progress monitoring
         */
        monitorEnhancedProgress: function() {
            if (!Woo2Shopify.migrationId) {
                return;
            }

            // Clear any existing interval
            if (Woo2Shopify.progressInterval) {
                clearInterval(Woo2Shopify.progressInterval);
            }

            // Start monitoring
            Woo2Shopify.progressInterval = setInterval(function() {
                Woo2Shopify.checkEnhancedProgress();
            }, 2000); // Check every 2 seconds

            // Initial check
            Woo2Shopify.checkEnhancedProgress();
        },

        checkEnhancedProgress: function() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'woo2shopify_get_enhanced_progress',
                    nonce: woo2shopify_admin.nonce,
                    migration_id: Woo2Shopify.migrationId
                },
                success: function(response) {
                    if (response.success && response.data) {
                        Woo2Shopify.updateEnhancedProgress(response.data);

                        // Stop monitoring if completed or failed
                        if (response.data.status === 'completed' || response.data.status === 'failed') {
                            clearInterval(Woo2Shopify.progressInterval);
                            Woo2Shopify.onMigrationComplete(response.data);
                        }
                    } else {
                        console.error('Progress check failed:', response);
                    }
                },
                error: function() {
                    console.error('Progress check error');
                }
            });
        },

        updateEnhancedProgress: function(data) {
            // Update progress bar
            var percentage = data.percentage || 0;
            $('.progress-fill').css('width', percentage + '%');
            $('.progress-text').text(percentage + '%');

            // Update progress details
            var detailsHtml = '';
            detailsHtml += 'Processed: ' + data.processed_products + '/' + data.total_products;
            detailsHtml += ' | Success: ' + data.successful_products;
            detailsHtml += ' | Failed: ' + data.failed_products;
            if (data.skipped_products > 0) {
                detailsHtml += ' | Skipped: ' + data.skipped_products;
            }
            detailsHtml += '<br>';
            detailsHtml += 'Batch: ' + data.current_batch + '/' + data.total_batches;
            detailsHtml += ' | Memory: ' + data.memory_usage;
            if (data.estimated_completion) {
                detailsHtml += ' | ETA: ' + new Date(data.estimated_completion).toLocaleTimeString();
            }

            $('.progress-details').html(detailsHtml);

            // Update status message
            if (data.status_message) {
                $('.progress-status').text(data.status_message);
            }
        },

        onMigrationComplete: function(data) {
            var message = '';
            var noticeClass = 'notice-success';

            if (data.status === 'completed') {
                message = 'Migration completed successfully! ';
                message += 'Processed: ' + data.processed_products + ' products. ';
                message += 'Success: ' + data.successful_products + ', ';
                message += 'Failed: ' + data.failed_products;
                if (data.skipped_products > 0) {
                    message += ', Skipped: ' + data.skipped_products;
                }
            } else {
                message = 'Migration failed: ' + (data.status_message || 'Unknown error');
                noticeClass = 'notice-error';
            }

            $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>')
                .insertAfter('.wrap h1')
                .delay(10000)
                .fadeOut();

            // Reset UI
            $('#start-migration').show();
            $('#stop-migration').hide();
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        Woo2Shopify.init();
    });
    
    // Make Woo2Shopify globally available
    window.Woo2Shopify = Woo2Shopify;
    
})(jQuery);
