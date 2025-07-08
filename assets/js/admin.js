/**
 * Woo2Shopify Admin JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';
    
    var Woo2Shopify = {
        
        /**
         * Initialize
         */
        init: function() {
            console.log('Woo2Shopify: Initializing...');
            this.bindEvents();
            this.initRangeSliders();
            this.initTabs();
            console.log('Woo2Shopify: Initialized successfully');
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

            // Initialize video migration tab
            if ($('.woo2shopify-video-migration').length) {
                this.initVideoMigration();
            }
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            console.log('Woo2Shopify: Binding events...');
            var self = this;
            $('#test-connection').on('click', function(e) { self.testConnection.call(this, e); });
            $('#test-batch').on('click', function(e) {
                console.log('Woo2Shopify: Test batch button clicked!');
                self.testBatch.call(this, e);
            });
            $('#start-migration').on('click', function(e) { self.startMigration.call(this, e); });
            $('#stop-migration').on('click', function(e) { self.stopMigration.call(this, e); });
            $('#force-continue-migration').on('click', function(e) { self.forceContinueMigration.call(this, e); });
            $('#debug-migration').on('click', function(e) { self.debugMigration.call(this, e); });
            $('#create-tables').on('click', function(e) { self.createTables.call(this, e); });
            $('#stop-all-tasks').on('click', function(e) { self.stopAllTasks.call(this, e); });
            $('#clear-logs').on('click', function(e) { self.clearLogs.call(this, e); });
            $('#clear-video-cache').on('click', function(e) { self.clearVideoCache.call(this, e); });
            $('#clear-shopify-products').on('click', function(e) { self.clearShopifyProducts.call(this, e); });
            $('#debug-languages').on('click', function(e) { self.debugLanguages.call(this, e); });
            $('#reset-video-failures').on('click', function(e) { self.resetVideoFailures.call(this, e); });

            // Selective migration events
            $('#filter-products').on('click', function(e) { self.filterProducts.call(this, e); });
            $('#reset-filters').on('click', function(e) { self.resetFilters.call(this, e); });
            $('#clear-search').on('click', function(e) { self.clearSearch.call(this, e); });
            $('#select-all-products').on('click', function(e) { self.selectAllProducts.call(this, e); });
            $('#deselect-all-products').on('click', function(e) { self.deselectAllProducts.call(this, e); });
            $('#migrate-selected-products').on('click', function(e) { self.migrateSelectedProducts.call(this, e); });
            $('#load-more-products').on('click', function(e) { self.loadMoreProducts.call(this, e); });

            // Page migration events
            $('#filter-pages').on('click', function(e) { self.filterPages.call(this, e); });
            $('#select-all-pages').on('click', function(e) { self.selectAllPages.call(this, e); });
            $('#deselect-all-pages').on('click', function(e) { self.deselectAllPages.call(this, e); });
            $('#migrate-selected-pages').on('click', function(e) { self.migrateSelectedPages.call(this, e); });
            $('#load-more-pages').on('click', function(e) { self.loadMorePages.call(this, e); });

            // Product selection change
            $(document).on('change', '.product-checkbox', function(e) { self.updateSelectedCount.call(this, e); });
            $(document).on('change', '.page-checkbox', function(e) { self.updateSelectedPagesCount.call(this, e); });

            // Real-time search
            $('#product-search').on('input', this.debounce(function() {
                Woo2Shopify.filterProducts({preventDefault: function(){}});
            }, 500));

            // Multi-language and currency option toggles
            $('#selective-include-translations').on('change', function() {
                $('#selective-language-options').toggle(this.checked);
            });

            $('#selective-include-currencies').on('change', function() {
                $('#selective-currency-options').toggle(this.checked);
            });

            // Enter key support for search
            $('#product-search').on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    Woo2Shopify.filterProducts({preventDefault: function(){}});
                }
            });

            // Video migration events
            $('#select-all-videos').on('click', function(e) { self.selectAllVideos.call(this, e); });
            $('#deselect-all-videos').on('click', function(e) { self.deselectAllVideos.call(this, e); });
            $('#test-selected-videos').on('click', function(e) { self.testSelectedVideos.call(this, e); });
            $('#video-migration-form').on('submit', function(e) { self.startVideoMigration.call(this, e); });

            // Video selection change
            $(document).on('change', 'input[name="selected_videos[]"]', function() {
                self.updateVideoMigrationButton();
            });
        },

        /**
         * Debounce function for search
         */
        debounce: function(func, wait) {
            var timeout;
            return function executedFunction() {
                var context = this;
                var args = Array.prototype.slice.call(arguments);
                var later = function() {
                    timeout = null;
                    func.apply(context, args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
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
         * Test batch processing system
         */
        testBatch: function(e) {
            console.log('Woo2Shopify: testBatch function called!');
            e.preventDefault();

            var $button = $(this);
            var $result = $('#batch-test-result');

            console.log('Woo2Shopify: Button element:', $button);
            console.log('Woo2Shopify: Result element:', $result);

            $button.addClass('loading').prop('disabled', true).text('Testing...');
            $result.hide().removeClass('success error');

            console.log('Woo2Shopify: Starting batch system test...');

            $.ajax({
                url: woo2shopify_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'woo2shopify_test_batch',
                    nonce: woo2shopify_ajax.nonce
                },
                success: function(response) {
                    console.log('Woo2Shopify: Batch test response:', response);

                    if (response.success) {
                        var data = response.data;
                        var resultHtml = '<div class="batch-test-results">';

                        // Functions test
                        resultHtml += '<h4>Functions Status:</h4><ul>';
                        for (var func in data.functions_exist) {
                            var status = data.functions_exist[func] ? '✅' : '❌';
                            resultHtml += '<li>' + status + ' ' + func + '</li>';
                        }
                        resultHtml += '</ul>';

                        // Database test
                        resultHtml += '<h4>Database:</h4>';
                        resultHtml += '<p>' + (data.table_exists ? '✅' : '❌') + ' Progress table exists</p>';
                        resultHtml += '<p>' + (data.test_insert ? '✅' : '❌') + ' Can insert/update records</p>';

                        // WP Cron test
                        resultHtml += '<h4>WP Cron:</h4>';
                        resultHtml += '<p>' + (data.wp_cron_enabled ? '✅' : '❌') + ' WP Cron enabled</p>';

                        // Classes test
                        resultHtml += '<h4>Classes Status:</h4><ul>';
                        for (var cls in data.classes_exist) {
                            var status = data.classes_exist[cls] ? '✅' : '❌';
                            resultHtml += '<li>' + status + ' ' + cls + '</li>';
                        }
                        resultHtml += '</ul></div>';

                        $result.html(resultHtml).addClass('success').show();
                    } else {
                        $result.html('<p><strong>Test Failed:</strong> ' + response.data.message + '</p>')
                               .addClass('error').show();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Woo2Shopify: Batch test error:', error);
                    $result.html('<p><strong>Test Error:</strong> ' + error + '</p>')
                           .addClass('error').show();
                },
                complete: function() {
                    $button.removeClass('loading').prop('disabled', false).text('Test Batch System');
                    console.log('Woo2Shopify: Batch test completed');
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

                            // Update UI for migration start
                            $('#start-migration').hide();
                            $('#stop-migration').show();
                            $('#migration-progress').show();
                            $('#migration-results').hide();

                            // Initialize progress display
                            Woo2Shopify.updateProgress(0, 0, response.data.total_products || 0, 'Migration started - initializing batch processing...');

                            // Start tracking progress
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
         * Force continue migration (when stuck)
         */
        forceContinueMigration: function(e) {
            e.preventDefault();

            if (!Woo2Shopify.currentMigrationId) {
                alert('No active migration found');
                return;
            }

            if (!confirm('This will force the migration to continue from where it left off. Are you sure?')) {
                return;
            }

            var $button = $(this);
            $button.prop('disabled', true).text('Forcing...');

            $.ajax({
                url: woo2shopify_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'woo2shopify_force_continue',
                    migration_id: Woo2Shopify.currentMigrationId,
                    nonce: woo2shopify_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('Migration force continued successfully. Check progress in a few seconds.');
                        // Continue tracking progress
                        Woo2Shopify.trackProgress(Woo2Shopify.currentMigrationId);
                    } else {
                        alert('Failed to force continue migration: ' + (response.data || 'Unknown error'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Force continue error:', xhr.responseText);
                    alert('Failed to force continue migration: ' + error);
                },
                complete: function() {
                    $button.prop('disabled', false).text('Force Continue Migration');
                }
            });
        },

        /**
         * Auto force continue migration (silent recovery)
         */
        autoForceContinue: function() {
            if (!Woo2Shopify.currentMigrationId) {
                console.error('Woo2Shopify: No active migration found for auto-recovery');
                return;
            }

            // Prevent multiple auto-recovery attempts
            if (Woo2Shopify.autoRecoveryInProgress) {
                console.log('Woo2Shopify: Auto-recovery already in progress, skipping...');
                return;
            }

            Woo2Shopify.autoRecoveryInProgress = true;
            console.log('Woo2Shopify: Starting auto-recovery for migration:', Woo2Shopify.currentMigrationId);

            $.ajax({
                url: woo2shopify_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'woo2shopify_force_continue',
                    migration_id: Woo2Shopify.currentMigrationId,
                    nonce: woo2shopify_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        console.log('Woo2Shopify: Auto-recovery successful, migration resumed');
                        $('#current-status').text('Migration resumed automatically');
                        // Reset stuck counter to prevent immediate re-trigger
                        Woo2Shopify.stuckCounter = 0;
                    } else {
                        console.error('Woo2Shopify: Auto-recovery failed:', response.data || 'Unknown error');
                        $('#current-status').text('Auto-recovery failed - manual intervention may be needed');
                        // Show manual force button as fallback
                        $('#force-continue-migration').show();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Woo2Shopify: Auto-recovery error:', xhr.responseText);
                    $('#current-status').text('Auto-recovery error - manual intervention may be needed');
                    // Show manual force button as fallback
                    $('#force-continue-migration').show();
                },
                complete: function() {
                    Woo2Shopify.autoRecoveryInProgress = false;
                }
            });
        },

        /**
         * Debug migration status
         */
        debugMigration: function(e) {
            e.preventDefault();

            var $button = $(this);
            $button.prop('disabled', true).text('Debugging...');

            $.ajax({
                url: woo2shopify_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'woo2shopify_debug_migration',
                    nonce: woo2shopify_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var debug = response.data;
                        var debugHtml = '<div class="woo2shopify-debug-info" style="background: #f9f9f9; padding: 15px; border-radius: 5px; margin: 10px 0; font-family: monospace; font-size: 12px; max-height: 400px; overflow-y: auto;">';

                        debugHtml += '<h4>🔍 Migration Debug Info</h4>';

                        // Recent migrations
                        debugHtml += '<h5>📊 Recent Migrations:</h5>';
                        if (debug.recent_migrations && debug.recent_migrations.length > 0) {
                            debug.recent_migrations.forEach(function(migration) {
                                debugHtml += '<div style="margin: 5px 0; padding: 5px; background: #fff; border-radius: 3px;">';
                                debugHtml += '<strong>ID:</strong> ' + migration.migration_id + '<br>';
                                debugHtml += '<strong>Status:</strong> ' + migration.status + '<br>';
                                debugHtml += '<strong>Progress:</strong> ' + migration.processed_products + '/' + migration.total_products + '<br>';
                                debugHtml += '<strong>Created:</strong> ' + migration.created_at + '<br>';
                                if (migration.status_message) {
                                    debugHtml += '<strong>Message:</strong> ' + migration.status_message + '<br>';
                                }
                                debugHtml += '</div>';
                            });
                        } else {
                            debugHtml += '<p>No recent migrations found</p>';
                        }

                        // Shopify connection
                        debugHtml += '<h5>🔗 Shopify Connection:</h5>';
                        if (debug.shopify_connection.success) {
                            debugHtml += '<span style="color: green;">✅ Connected</span><br>';
                            debugHtml += debug.shopify_connection.message + '<br>';
                        } else {
                            debugHtml += '<span style="color: red;">❌ Failed</span><br>';
                            debugHtml += debug.shopify_connection.message + '<br>';
                        }

                        // Product count
                        debugHtml += '<h5>📦 Product Count:</h5>';
                        debugHtml += debug.product_count + ' products found<br>';

                        // WP Cron status
                        debugHtml += '<h5>⏰ WP Cron Status:</h5>';
                        if (debug.wp_cron_disabled) {
                            debugHtml += '<span style="color: orange;">⚠️ WP Cron is disabled</span><br>';
                        } else {
                            debugHtml += '<span style="color: green;">✅ WP Cron is enabled</span><br>';
                        }

                        // Recent logs
                        debugHtml += '<h5>📝 Recent Logs:</h5>';
                        if (debug.recent_logs && debug.recent_logs.length > 0) {
                            debug.recent_logs.forEach(function(log) {
                                var levelColor = log.level === 'error' ? 'red' : (log.level === 'warning' ? 'orange' : 'green');
                                debugHtml += '<div style="margin: 2px 0; color: ' + levelColor + ';">';
                                debugHtml += '[' + log.created_at + '] ' + log.level.toUpperCase() + ': ' + log.message;
                                debugHtml += '</div>';
                            });
                        } else {
                            debugHtml += '<p>No recent logs found</p>';
                        }

                        debugHtml += '</div>';

                        // Show debug info in a modal-like overlay
                        var $overlay = $('<div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 9999; display: flex; align-items: center; justify-content: center;">');
                        var $modal = $('<div style="background: white; padding: 20px; border-radius: 8px; max-width: 80%; max-height: 80%; overflow-y: auto; position: relative;">');
                        var $closeBtn = $('<button style="position: absolute; top: 10px; right: 10px; background: #ccc; border: none; padding: 5px 10px; cursor: pointer; border-radius: 3px;">Close</button>');

                        $modal.append($closeBtn);
                        $modal.append(debugHtml);
                        $overlay.append($modal);
                        $('body').append($overlay);

                        $closeBtn.on('click', function() {
                            $overlay.remove();
                        });

                        $overlay.on('click', function(e) {
                            if (e.target === $overlay[0]) {
                                $overlay.remove();
                            }
                        });

                    } else {
                        alert('Debug failed: ' + (response.data ? response.data.message : 'Unknown error'));
                    }
                },
                error: function() {
                    alert('Debug failed due to server error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Debug Migration');
                }
            });
        },

        /**
         * Create database tables
         */
        createTables: function(e) {
            e.preventDefault();

            if (!confirm('Create database tables? This will recreate all Woo2Shopify tables.')) {
                return;
            }

            var $button = $(this);
            $button.prop('disabled', true).text('Creating Tables...');

            $.ajax({
                url: woo2shopify_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'woo2shopify_create_tables',
                    nonce: woo2shopify_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('Database tables created successfully!\n\nTables exist: ' +
                              (response.data.tables_exist ? 'YES' : 'NO') +
                              '\nDB Version: ' + response.data.db_version);
                    } else {
                        alert('Failed to create tables: ' + (response.data ? response.data.message : 'Unknown error'));
                    }
                },
                error: function() {
                    alert('Table creation failed due to server error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Create Database Tables');
                }
            });
        },

        /**
         * Stop all background tasks
         */
        stopAllTasks: function(e) {
            e.preventDefault();

            if (!confirm('⚠️ This will stop ALL running migrations and background tasks!\n\nAre you sure you want to continue?')) {
                return;
            }

            var $button = $(this);
            $button.prop('disabled', true).text('Stopping Tasks...');

            $.ajax({
                url: woo2shopify_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'woo2shopify_stop_all_tasks',
                    nonce: woo2shopify_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var message = 'All background tasks stopped!\n\n';
                        message += 'Stopped migrations: ' + response.data.stopped_migrations + '\n';
                        message += 'WP Cron: ' + response.data.wp_cron_status + '\n';
                        if (response.data.action_scheduler_jobs) {
                            message += 'ActionScheduler jobs: ' + response.data.action_scheduler_jobs;
                        }
                        alert(message);

                        // Reset UI
                        $('#start-migration').show();
                        $('#stop-migration').hide();
                        $('#force-continue-migration').hide();
                        $('#migration-progress').hide();

                        // Clear any running intervals
                        if (Woo2Shopify.progressInterval) {
                            clearInterval(Woo2Shopify.progressInterval);
                        }
                    } else {
                        alert('Failed to stop tasks: ' + (response.data ? response.data.message : 'Unknown error'));
                    }
                },
                error: function() {
                    alert('Failed to stop tasks due to server error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Stop All Background Tasks');
                }
            });
        },

        /**
         * Track migration progress
         */
        trackProgress: function(migrationId) {
            Woo2Shopify.lastProcessedCount = 0;
            Woo2Shopify.stuckCounter = 0;
            Woo2Shopify.autoRecoveryInProgress = false;
            Woo2Shopify.progressErrorCount = 0;
            Woo2Shopify.migrationStartTime = Date.now(); // Track when migration started

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
                        console.log('Progress response:', response);

                        if (response.success && response.data) {
                            var data = response.data;
                            console.log('Progress data:', data);

                            // Reset error counter on successful response
                            Woo2Shopify.progressErrorCount = 0;

                            // Calculate percentage - use backend percentage if available
                            var percentage = 0;
                            if (data.percentage !== undefined && data.percentage !== null) {
                                percentage = Math.round(parseFloat(data.percentage));
                            } else if (data.total_products > 0) {
                                percentage = Math.round((data.processed_products / data.total_products) * 100);
                            }
                            percentage = Math.min(percentage, 100); // Cap at 100%

                            // Update progress display with enhanced information
                            Woo2Shopify.updateProgress(
                                percentage,
                                data.processed_products || 0,
                                data.total_products || 0,
                                data.status_message || 'Processing...'
                            );

                            // Update additional stats if available
                            if (data.successful_products !== undefined) {
                                $('#successful-count').text(data.successful_products);
                            }
                            if (data.failed_products !== undefined) {
                                $('#failed-count').text(data.failed_products);
                            }

                            // Show progress bar if hidden
                            if ($('#migration-progress').is(':hidden')) {
                                $('#migration-progress').show();
                            }

                            // Check if migration is stuck and auto-recover
                            // But give it a grace period of 60 seconds after migration start
                            var timeSinceStart = Date.now() - Woo2Shopify.migrationStartTime;
                            var gracePeriod = 60000; // 60 seconds

                            if (data.processed_products === Woo2Shopify.lastProcessedCount && data.status === 'running') {
                                if (timeSinceStart < gracePeriod) {
                                    // Still in grace period - don't count as stuck
                                    var remainingGrace = Math.ceil((gracePeriod - timeSinceStart) / 1000);
                                    $('#current-status').text('Migration starting - please wait (' + remainingGrace + 's)...');
                                } else {
                                    // Grace period over, start counting stuck
                                    Woo2Shopify.stuckCounter++;

                                    // Show warning after 30 seconds (20 checks) post-grace
                                    if (Woo2Shopify.stuckCounter >= 20) {
                                        $('#current-status').text('Migration processing - please wait...');
                                    }

                                    // Auto-recover after 60 seconds (40 checks) post-grace without progress
                                    if (Woo2Shopify.stuckCounter >= 40) {
                                        console.log('Woo2Shopify: Migration appears stuck after 60 seconds post-grace, attempting auto-recovery...');
                                        $('#current-status').text('Migration temporarily paused - auto-recovering...');

                                        // Auto-trigger force continue instead of showing button
                                        Woo2Shopify.autoForceContinue();
                                    }
                                }
                            } else {
                                Woo2Shopify.stuckCounter = 0;
                                // Hide force button if it was shown (fallback)
                                $('#force-continue-migration').hide();
                            }

                            Woo2Shopify.lastProcessedCount = data.processed_products;

                            // Check if completed
                            if (data.status === 'completed' || data.status === 'failed') {
                                clearInterval(Woo2Shopify.progressInterval);
                                $('#force-continue-migration').hide();
                                Woo2Shopify.showResults(data);
                            }
                        } else {
                            console.error('Invalid progress response:', response);

                            // Handle error response
                            if (response.data && response.data.message) {
                                console.error('Progress error message:', response.data.message);
                                $('#current-status').text('Error: ' + response.data.message);
                            } else {
                                $('#current-status').text('Progress update failed - retrying...');
                            }

                            // Increment error counter for invalid responses too
                            Woo2Shopify.progressErrorCount++;
                            if (Woo2Shopify.progressErrorCount >= 5) {
                                clearInterval(Woo2Shopify.progressInterval);
                                Woo2Shopify.showError('Progress tracking failed after multiple attempts');
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Progress tracking error:', {xhr: xhr, status: status, error: error});

                        // Increment error counter
                        if (!Woo2Shopify.progressErrorCount) {
                            Woo2Shopify.progressErrorCount = 0;
                        }
                        Woo2Shopify.progressErrorCount++;

                        // Stop tracking only after multiple consecutive errors
                        if (Woo2Shopify.progressErrorCount >= 5) {
                            clearInterval(Woo2Shopify.progressInterval);
                            Woo2Shopify.showError('Failed to get progress updates after multiple attempts');
                        } else {
                            $('#current-status').text('Connection issue - retrying... (' + Woo2Shopify.progressErrorCount + '/5)');
                        }
                    },
                    complete: function() {
                        // Reset error counter on successful request
                        if (Woo2Shopify.progressErrorCount > 0) {
                            Woo2Shopify.progressErrorCount = 0;
                        }
                    }
                });
            }, 1500); // Check every 1.5 seconds for more responsive updates
        },
        
        /**
         * Update progress display
         */
        updateProgress: function(percentage, processed, total, status) {
            // Ensure values are numbers
            percentage = parseInt(percentage) || 0;
            processed = parseInt(processed) || 0;
            total = parseInt(total) || 0;

            // Update progress bar with animation
            $('.progress-fill').css('width', percentage + '%');
            $('.progress-text').text(percentage + '%');

            // Update counts
            $('#processed-count').text(processed);
            $('#total-count').text(total);

            // Update status with timestamp
            var timestamp = new Date().toLocaleTimeString();
            $('#current-status').text(status + ' (' + timestamp + ')');

            // Add visual feedback for progress changes
            if (percentage > 0) {
                $('.progress-fill').addClass('progress-active');
            }

            // Update page title with progress
            if (percentage > 0) {
                document.title = 'Migration ' + percentage + '% - Woo2Shopify';
            }

            // Log progress for debugging
            console.log('Progress updated:', {
                percentage: percentage,
                processed: processed,
                total: total,
                status: status
            });
        },
        
        /**
         * Show migration results
         */
        showResults: function(data) {
            // Reset page title
            document.title = 'Woo2Shopify - Migration Complete';

            // Hide progress, show controls
            $('#migration-progress').hide();
            $('#start-migration').show();
            $('#stop-migration').hide();

            // Update final counts
            $('#success-count').text(data.successful_products || 0);
            $('#failed-count').text(data.failed_products || 0);
            $('#migration-results').show();

            // Show completion message with details
            var message = '';
            if (data.status === 'completed') {
                message = '✅ Migration completed successfully! Processed ' + (data.processed_products || 0) + ' products.';
            } else if (data.status === 'failed') {
                message = '❌ Migration failed. Processed ' + (data.processed_products || 0) + ' products before failure.';
            }

            Woo2Shopify.showNotice(message, data.status === 'completed' ? 'success' : 'error');

            // Clear migration ID
            Woo2Shopify.currentMigrationId = null;

            // Log completion
            console.log('Migration completed:', data);
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
                url: woo2shopify_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'woo2shopify_clear_video_cache',
                    nonce: woo2shopify_ajax.nonce
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
         * Reset video failures
         */
        resetVideoFailures: function(e) {
            e.preventDefault();

            var $button = $(this);

            // Confirm action
            if (!confirm('Are you sure you want to reset video failures? This will re-enable video processing.')) {
                return;
            }

            // Show loading state
            $button.addClass('loading').prop('disabled', true).text('Resetting...');

            console.log('Woo2Shopify: Resetting video failures...');

            // Make AJAX request
            $.ajax({
                url: woo2shopify_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'woo2shopify_reset_video_failures',
                    nonce: woo2shopify_ajax.nonce
                },
                success: function(response) {
                    console.log('Woo2Shopify: Video failures reset response:', response);

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
                    console.error('Woo2Shopify: Video failures reset error:', error);
                    alert('Error resetting video failures: ' + error);
                },
                complete: function() {
                    // Reset button state
                    $button.removeClass('loading').prop('disabled', false).text('Reset Video Failures');
                }
            });
        },

        /**
         * Initialize video migration
         */
        initVideoMigration: function() {
            console.log('Woo2Shopify: Initializing video migration...');
            this.updateVideoMigrationButton();
        },

        /**
         * Select all videos
         */
        selectAllVideos: function(e) {
            e.preventDefault();
            $('input[name="selected_videos[]"]').prop('checked', true);
            this.updateVideoMigrationButton();
        },

        /**
         * Deselect all videos
         */
        deselectAllVideos: function(e) {
            e.preventDefault();
            $('input[name="selected_videos[]"]').prop('checked', false);
            this.updateVideoMigrationButton();
        },

        /**
         * Update video migration button state
         */
        updateVideoMigrationButton: function() {
            var selectedCount = $('input[name="selected_videos[]"]:checked').length;
            $('#start-video-migration').prop('disabled', selectedCount === 0);

            if (selectedCount > 0) {
                $('#start-video-migration').text('Migrate ' + selectedCount + ' Selected Videos');
            } else {
                $('#start-video-migration').text('Migrate Selected Videos');
            }
        },

        /**
         * Test selected videos
         */
        testSelectedVideos: function(e) {
            e.preventDefault();

            var selectedVideos = [];
            $('input[name="selected_videos[]"]:checked').each(function() {
                selectedVideos.push($(this).val());
            });

            if (selectedVideos.length === 0) {
                alert('Please select videos to test.');
                return;
            }

            var $button = $(this);
            $button.prop('disabled', true).text('Testing...');

            console.log('Woo2Shopify: Testing selected videos:', selectedVideos);

            // Reset all status indicators
            $('.video-status').each(function() {
                $(this).find('.status-indicator').text('⏳');
                $(this).find('.status-text').text('Testing...');
            });

            // Test each video
            var testPromises = selectedVideos.map(function(videoUrl) {
                return Woo2Shopify.testSingleVideo(videoUrl);
            });

            Promise.all(testPromises).then(function() {
                $button.prop('disabled', false).text('Test Selected Videos');
                console.log('Woo2Shopify: Video testing completed');
            }).catch(function(error) {
                console.error('Woo2Shopify: Video testing error:', error);
                $button.prop('disabled', false).text('Test Selected Videos');
            });
        },

        /**
         * Test single video
         */
        testSingleVideo: function(videoUrl) {
            return new Promise(function(resolve, reject) {
                $.ajax({
                    url: woo2shopify_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'woo2shopify_test_video',
                        video_url: videoUrl,
                        nonce: woo2shopify_ajax.nonce
                    },
                    timeout: 10000, // 10 second timeout per video
                    success: function(response) {
                        var $status = $('.video-status[data-video-url="' + videoUrl + '"]');

                        if (response.success) {
                            $status.find('.status-indicator').text('✅');
                            $status.find('.status-text').text('Ready to migrate');
                        } else {
                            $status.find('.status-indicator').text('❌');
                            $status.find('.status-text').text('Error: ' + (response.data.message || 'Unknown error'));
                        }
                        resolve();
                    },
                    error: function(xhr, status, error) {
                        var $status = $('.video-status[data-video-url="' + videoUrl + '"]');
                        $status.find('.status-indicator').text('❌');
                        $status.find('.status-text').text('Connection error');
                        resolve(); // Don't reject, just mark as failed
                    }
                });
            });
        },

        /**
         * Start video migration
         */
        startVideoMigration: function(e) {
            e.preventDefault();

            var selectedVideos = [];
            $('input[name="selected_videos[]"]:checked').each(function() {
                selectedVideos.push({
                    url: $(this).val(),
                    product_id: $(this).data('product-id')
                });
            });

            if (selectedVideos.length === 0) {
                alert('Please select videos to migrate.');
                return;
            }

            if (!confirm('Are you sure you want to migrate ' + selectedVideos.length + ' selected videos?')) {
                return;
            }

            console.log('Woo2Shopify: Starting video migration for:', selectedVideos);

            // Show progress
            $('.video-migration-progress').show();
            $('#start-video-migration').prop('disabled', true).text('Migrating...');

            // Start migration
            this.processVideoMigration(selectedVideos, 0);
        },

        /**
         * Process video migration
         */
        processVideoMigration: function(videos, currentIndex) {
            var self = this;

            if (currentIndex >= videos.length) {
                // Migration completed
                $('.video-migration-progress').hide();
                $('#start-video-migration').prop('disabled', false).text('Migrate Selected Videos');
                $('#video-migration-results').show();
                $('.results-summary').html('<p>Video migration completed successfully!</p>');
                return;
            }

            var video = videos[currentIndex];
            var progress = Math.round(((currentIndex + 1) / videos.length) * 100);

            // Update progress
            $('.progress-fill').css('width', progress + '%');
            $('.progress-text').text(progress + '%');

            console.log('Woo2Shopify: Migrating video ' + (currentIndex + 1) + '/' + videos.length + ':', video.url);

            $.ajax({
                url: woo2shopify_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'woo2shopify_migrate_single_video',
                    video_url: video.url,
                    product_id: video.product_id,
                    nonce: woo2shopify_ajax.nonce
                },
                timeout: 30000, // 30 second timeout per video
                success: function(response) {
                    console.log('Woo2Shopify: Video migration response:', response);

                    // Continue with next video
                    setTimeout(function() {
                        self.processVideoMigration(videos, currentIndex + 1);
                    }, 1000); // 1 second delay between videos
                },
                error: function(xhr, status, error) {
                    console.error('Woo2Shopify: Video migration error:', error);

                    // Continue with next video even if this one failed
                    setTimeout(function() {
                        self.processVideoMigration(videos, currentIndex + 1);
                    }, 1000);
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
            var productType = $('#product-type').val();
            var migrationStatus = $('#migration-status').val();
            var priceMin = $('#price-min').val();
            var priceMax = $('#price-max').val();

            Woo2Shopify.loadProducts({
                search: search,
                category: category,
                status: status,
                product_type: productType,
                migration_status: migrationStatus,
                price_min: priceMin,
                price_max: priceMax,
                offset: 0
            });
        },

        /**
         * Reset filters
         */
        resetFilters: function(e) {
            e.preventDefault();

            $('#product-search').val('');
            $('#product-category').val('');
            $('#product-status').val('any');
            $('#product-type').val('');
            $('#migration-status').val('');
            $('#price-min').val('');
            $('#price-max').val('');

            Woo2Shopify.loadProducts({
                offset: 0
            });
        },

        /**
         * Clear search
         */
        clearSearch: function(e) {
            e.preventDefault();
            $('#product-search').val('').focus();
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
                status: 'any',
                product_type: '',
                migration_status: '',
                price_min: '',
                price_max: ''
            };

            params = $.extend(defaults, params);

            // Show loading indicator
            if (params.offset === 0) {
                $('#products-list').html('<div class="loading-indicator"><span class="spinner is-active"></span> Loading products...</div>');
            }

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
                    status: params.status,
                    product_type: params.product_type,
                    migration_status: params.migration_status,
                    price_min: params.price_min,
                    price_max: params.price_max
                },
                success: function(response) {
                    if (response.success) {
                        if (params.offset === 0) {
                            $('#products-list').empty();
                        }

                        Woo2Shopify.renderProducts(response.data.products);

                        // Update results count
                        var resultsText = response.data.total + ' products found';
                        if (params.search || params.category || params.status !== 'any' ||
                            params.product_type || params.migration_status || params.price_min || params.price_max) {
                            resultsText += ' (filtered)';
                        }
                        $('#filter-results-count').text(resultsText);

                        // Handle load more button
                        if (!response.data.has_more) {
                            $('#load-more-products').hide();
                        } else {
                            $('#load-more-products').show().data('offset', params.offset + params.limit);
                        }

                        // Show message if no products found
                        if (response.data.products.length === 0 && params.offset === 0) {
                            $('#products-list').html('<div class="no-products-found"><p>No products found matching your criteria.</p></div>');
                        }
                    } else {
                        alert('Error loading products: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('Error loading products');
                    if (params.offset === 0) {
                        $('#products-list').html('<div class="error-message"><p>Error loading products. Please try again.</p></div>');
                    }
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
                    '<img src="' + product.image + '" alt="' + product.title + '" loading="lazy">' :
                    '<div class="no-image"><span class="dashicons dashicons-format-image"></span></div>';

                var statusClass = product.migrated ? 'migrated' : '';
                var statusText = product.migrated ? 'Already Migrated' : 'Ready to Migrate';
                var statusIcon = product.migrated ? 'dashicons-yes-alt' : 'dashicons-migrate';

                // Product type badge
                var typeClass = 'product-type-' + (product.type || 'simple');
                var typeBadge = '<span class="product-type-badge ' + typeClass + '">' +
                               (product.type || 'simple').charAt(0).toUpperCase() + (product.type || 'simple').slice(1) +
                               '</span>';

                // Price formatting
                var priceHtml = '';
                if (product.price) {
                    if (product.sale_price && product.sale_price !== product.price) {
                        priceHtml = '<span class="price-regular">$' + product.regular_price + '</span> ' +
                                   '<span class="price-sale">$' + product.sale_price + '</span>';
                    } else {
                        priceHtml = '<span class="price">$' + product.price + '</span>';
                    }
                } else {
                    priceHtml = '<span class="price-na">Price not set</span>';
                }

                // Variation count
                var variationInfo = '';
                if (product.type === 'variable' && product.variation_count) {
                    variationInfo = '<span class="variation-count">' + product.variation_count + ' variations</span>';
                }

                // Language tags
                var languageTags = '';
                if (product.languages && product.languages.length > 0) {
                    languageTags = '<div class="language-tags">';
                    product.languages.forEach(function(lang) {
                        languageTags += '<span class="language-tag">' + lang + '</span>';
                    });
                    languageTags += '</div>';
                }

                html += '<div class="product-item ' + statusClass + '" data-product-id="' + product.id + '">';
                html += '<label class="product-label">';
                html += '<input type="checkbox" class="product-checkbox" value="' + product.id + '"' +
                        (product.migrated ? ' disabled' : '') + '>';
                html += '<div class="product-info">';
                html += '<div class="product-image">' + imageHtml + '</div>';
                html += '<div class="product-details">';
                html += '<div class="product-header">';
                html += '<h4 class="product-title">' + product.title + '</h4>';
                html += typeBadge;
                html += '</div>';
                html += '<div class="product-meta">';
                html += '<div class="meta-row">';
                html += '<span class="meta-label">SKU:</span> <span class="meta-value">' + (product.sku || 'N/A') + '</span>';
                html += '</div>';
                html += '<div class="meta-row">';
                html += '<span class="meta-label">Price:</span> <span class="meta-value">' + priceHtml + '</span>';
                html += '</div>';
                if (variationInfo) {
                    html += '<div class="meta-row">' + variationInfo + '</div>';
                }
                html += '<div class="meta-row">';
                html += '<span class="meta-label">Categories:</span> <span class="meta-value">' + (product.categories.join(', ') || 'None') + '</span>';
                html += '</div>';
                html += '</div>';
                if (languageTags) {
                    html += languageTags;
                }
                html += '<div class="product-status">';
                html += '<span class="dashicons ' + statusIcon + '"></span>';
                html += '<span class="status-text">' + statusText + '</span>';
                html += '</div>';
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

            // Collect all migration options
            var selectedLanguages = [];
            $('input[name="selective_languages[]"]:checked').each(function() {
                selectedLanguages.push($(this).val());
            });

            var selectedCurrencies = [];
            $('input[name="selective_currencies[]"]:checked').each(function() {
                selectedCurrencies.push($(this).val());
            });

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
                    include_categories: $('#selective-include-categories').is(':checked'),
                    include_tags: $('#selective-include-tags').is(':checked'),
                    include_translations: $('#selective-include-translations').is(':checked'),
                    include_currencies: $('#selective-include-currencies').is(':checked'),
                    include_seo: $('#selective-include-seo').is(':checked'),
                    include_custom_fields: $('#selective-include-custom-fields').is(':checked'),
                    skip_duplicates: $('#selective-skip-duplicates').is(':checked'),
                    update_existing: $('#selective-update-existing').is(':checked'),
                    selected_languages: selectedLanguages,
                    selected_currencies: selectedCurrencies
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
        },

        /**
         * Clear Shopify products (for testing)
         */
        clearShopifyProducts: function(e) {
            e.preventDefault();

            if (!confirm('⚠️ WARNING: This will DELETE ALL products from your Shopify store!\n\nThis is for testing purposes only. Are you absolutely sure?')) {
                return;
            }

            if (!confirm('This action cannot be undone! All products will be permanently deleted from Shopify. Continue?')) {
                return;
            }

            var $button = $(this);
            $button.prop('disabled', true).text('Deleting Products...');

            $.ajax({
                url: woo2shopify_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'woo2shopify_clear_shopify_products',
                    nonce: woo2shopify_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('✅ Deleted ' + response.data.deleted_count + ' products from Shopify');
                        console.log('Cleared products:', response.data);
                    } else {
                        alert('Failed to clear products: ' + (response.data.message || 'Unknown error'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Clear products error:', xhr.responseText);
                    alert('Failed to clear products due to server error: ' + error);
                },
                complete: function() {
                    $button.prop('disabled', false).text('Clear Shopify Products (Test)');
                }
            });
        },

        /**
         * Debug language settings
         */
        debugLanguages: function(e) {
            e.preventDefault();

            var $button = $(this);
            $button.prop('disabled', true).text('Getting Language Info...');

            $.ajax({
                url: woo2shopify_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'woo2shopify_debug_languages',
                    nonce: woo2shopify_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        console.log('Language Debug Info:', response.data);

                        var info = response.data;
                        var message = 'Language Debug Info:\n\n';

                        if (info.wpml.active) {
                            message += 'WPML: Active\n';
                            message += '- Default: ' + info.wpml.default_language + '\n';
                            message += '- Current: ' + info.wpml.current_language + '\n';
                            message += '- Languages: ' + Object.keys(info.wpml.active_languages).join(', ') + '\n\n';
                        } else {
                            message += 'WPML: Not Active\n\n';
                        }

                        if (info.polylang.active) {
                            message += 'Polylang: Active\n';
                            message += '- Default: ' + info.polylang.default_language + '\n';
                            message += '- Current: ' + info.polylang.current_language + '\n';
                            message += '- Languages: ' + info.polylang.languages.join(', ') + '\n\n';
                        } else {
                            message += 'Polylang: Not Active\n\n';
                        }

                        message += 'Sample Products:\n';
                        info.sample_products.forEach(function(product) {
                            message += '- ' + product.name + ' (ID: ' + product.id + ') - Translations: ' + product.translations.join(', ') + '\n';
                        });

                        alert(message);
                    } else {
                        alert('Failed to get language info: ' + (response.data.message || 'Unknown error'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Language debug error:', xhr.responseText);
                    alert('Failed to get language info: ' + error);
                },
                complete: function() {
                    $button.prop('disabled', false).text('Debug Languages');
                }
            });
        }
    };

    // Initialize
    Woo2Shopify.init();

    // Make Woo2Shopify globally available
    window.Woo2Shopify = Woo2Shopify;

});
