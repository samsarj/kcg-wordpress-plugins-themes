/**
 * Frontend JavaScript - Autosave and interaction handling with real-time updates
 */

jQuery(document).ready(function($) {
    var saveTimeout;
    var membersCache = [];
    var apiAssignedRoles = {}; // Track which roles are assigned from API
    var weekData = {}; // Store current week data for reference
    var lastUpdateTime = Math.floor(Date.now() / 1000); // Track last update for polling
    var updatePollInterval = null;
    
    // Load members first, then status
    loadMembers();
    
    // Start polling for real-time updates
    startUpdatePolling();
    
    // Handle checkbox changes
    $(document).on('change', '.checklist-checkbox', function() {
        var itemId = $(this).data('item-id');
        var $statusEl = $('#auto-save-status');
        
        // Show saving status
        $statusEl
            .removeClass('error')
            .addClass('saving')
            .text('Saving...');
        
        clearTimeout(saveTimeout);
        
        // Use a short timeout to debounce multiple rapid changes
        saveTimeout = setTimeout(function() {
            saveItem(itemId);
        }, 300);
    });
    
    // Handle volunteer dropdown changes to auto-save
    $(document).on('change', '.volunteer-dropdown', function() {
        var role = $(this).data('role');
        var $dropdown = $(this);
        var personId = $dropdown.val();
        
        // If a volunteer is selected, auto-save
        if (personId) {
            assignVolunteer(role, personId);
        } else {
            // If the dropdown is cleared, remove the volunteer assignment
            removeVolunteer(role);
        }
    });
    
    /**
     * Start polling for real-time updates from other clients
     */
    function startUpdatePolling() {
        // Poll every 2 seconds for updates
        updatePollInterval = setInterval(function() {
            pollForUpdates();
        }, 2000);
    }
    
    /**
     * Poll for updates from other clients
     */
    function pollForUpdates() {
        var currentOrigin = window.location.origin;
        var ajaxUrl = currentOrigin + '/wp-admin/admin-ajax.php';
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'kcg_checklist_get_updates',
                nonce: kcgChecklist.nonce,
                since: lastUpdateTime,
            },
            xhrFields: {
                withCredentials: true
            },
            success: function(response) {
                if (response.success && response.data.updates.length > 0) {
                    // Update the last update time
                    lastUpdateTime = response.data.current_time;
                    
                    // Process each update
                    $.each(response.data.updates, function(index, update) {
                        applyRemoteUpdate(update.item_id, update.value);
                    });
                }
            },
            error: function(xhr, status, err) {
                console.log('Error polling for updates:', err);
            }
        });
    }
    
    /**
     * Apply updates from other clients to the UI
     */
    function applyRemoteUpdate(itemId, value) {
        // Don't update the UI if it was just changed by this client
        var $element = $('[data-item-id="' + itemId + '"]');
        
        if ($element.length === 0) {
            return;
        }
        
        // Update local weekData
        weekData[itemId] = value;
        
        // Check if this is a volunteer role update
        if (typeof value === 'object' && value !== null && value.id) {
            // Volunteer assignment
            selectVolunteerById(itemId, value.id);
            
            // Update checkbox state if provided
            if (typeof value.checked !== 'undefined') {
                var $checkbox = $('[data-item-id="' + itemId + '"]');
                $checkbox.prop('checked', value.checked);
            }
        } else if (typeof value === 'boolean') {
            // Regular checkbox state
            $element.prop('checked', value);
        } else if (value === false) {
            // Volunteer removed
            $element.prop('checked', false);
        }
    }
    
    /**
     * Load members from Elvanto
     */
    function loadMembers() {
        var currentOrigin = window.location.origin;
        var ajaxUrl = currentOrigin + '/wp-admin/admin-ajax.php';
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'kcg_checklist_get_members',
                nonce: kcgChecklist.nonce,
            },
            xhrFields: {
                withCredentials: true
            },
            success: function(response) {
                if (response.success) {
                    membersCache = response.data.members;
                    // After members are loaded, load status
                    loadStatus();
                } else {
                    console.error('Error loading members:', response.data);
                }
            },
            error: function(xhr, status, err) {
                console.error('Error loading members:', err);
            }
        });
    }
    
    /**
     * Populate volunteer dropdowns with members
     */
    function populateDropdowns() {
        $('.volunteer-dropdown').each(function() {
            var $dropdown = $(this);
            var role = $dropdown.data('role');
            
            // Get friendly role name
            var roleLabel = 'volunteer';
            if (role === 'reader_assigned') {
                roleLabel = 'Reader';
            } else if (role === 'prayer_assigned') {
                roleLabel = 'Prayer';
            }
            
            // Preserve the first empty option
            var html = '<option value="">-- Select ' + roleLabel + ' --</option>';
            
            // Add all members
            $.each(membersCache, function(index, member) {
                var name = member.firstname + ' ' + member.lastname;
                html += '<option value="' + member.id + '">' + name + '</option>';
            });
            
            $dropdown.html(html);
        });
    }
    
    /**
     * Restore saved volunteer selections from week data
     */
    function restoreSavedSelections() {
        $.each(weekData, function(itemId, valueOrData) {
            // Check for volunteer assignments (object or string values for reader_assigned, prayer_assigned)
            if (typeof valueOrData === 'object' && valueOrData !== null && valueOrData.id) {
                // New format: { id: '...', name: '...', checked: true/false }
                selectVolunteerById(itemId, valueOrData.id);
                apiAssignedRoles[itemId] = true;
                
                // Restore checkbox state if present
                if (typeof valueOrData.checked !== 'undefined') {
                    var $checkbox = $('[data-item-id="' + itemId + '"]');
                    $checkbox.prop('checked', valueOrData.checked);
                }
            } else if (typeof valueOrData === 'string' && valueOrData.length > 0) {
                // Legacy format: just the name (for backwards compatibility)
                selectVolunteerByName(itemId, valueOrData);
            }
        });
    }
    
    /**
     * Save a single item
     */
    function saveItem(itemId) {
        var $checkbox = $('[data-item-id="' + itemId + '"]');
        var isChecked = $checkbox.is(':checked');
        var $statusEl = $('#auto-save-status');
        
        var currentOrigin = window.location.origin;
        var ajaxUrl = currentOrigin + '/wp-admin/admin-ajax.php';
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'kcg_checklist_toggle',
                nonce: kcgChecklist.nonce,
                item_id: itemId,
            },
            success: function(response) {
                if (response.success) {
                    $statusEl
                        .removeClass('saving error')
                        .text('All changes saved')
                        .delay(2000)
                        .fadeOut(function() {
                            $(this).fadeIn();
                        });
                } else {
                    $statusEl
                        .removeClass('saving')
                        .addClass('error')
                        .text('Error saving. Please try again.');
                }
            },
            error: function() {
                $statusEl
                    .removeClass('saving')
                    .addClass('error')
                    .text('Error saving. Please try again.');
            }
        });
    }
    
    /**
     * Assign volunteer to role
     */
    function assignVolunteer(role, personId) {
        var currentOrigin = window.location.origin;
        var ajaxUrl = currentOrigin + '/wp-admin/admin-ajax.php';
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'kcg_checklist_update_volunteer',
                nonce: kcgChecklist.nonce,
                role: role,
                person_id: personId,
            },
            success: function(response) {
                if (response.success) {
                    // Mark as API-assigned
                    apiAssignedRoles[role] = true;
                    
                    // Auto-check the checkbox for this item
                    var $checkbox = $('[data-item-id="' + role + '"]');
                    if ($checkbox.length) {
                        $checkbox.prop('checked', true);
                        // Trigger change event to save the checkbox state
                        // The toggle function now preserves volunteer data while updating checkbox state
                        $checkbox.trigger('change');
                    }
                } else {
                    var errorMsg = response.data.message || response.data.error || 'Unknown error';
                    console.error('Assignment error:', response.data);
                    alert('Error: ' + errorMsg);
                    apiAssignedRoles[role] = false;
                }
            },
            error: function(xhr, status, err) {
                console.error('AJAX error:', err, xhr.responseText);
                alert('Error assigning volunteer: ' + err);
                apiAssignedRoles[role] = false;
            }
        });
    }
    
    /**
     * Remove volunteer assignment for a role
     */
    function removeVolunteer(role) {
        var currentOrigin = window.location.origin;
        var ajaxUrl = currentOrigin + '/wp-admin/admin-ajax.php';
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'kcg_checklist_remove_volunteer',
                nonce: kcgChecklist.nonce,
                role: role,
            },
            success: function(response) {
                if (response.success) {
                    // Uncheck the checkbox
                    var $checkbox = $('[data-item-id="' + role + '"]');
                    if ($checkbox.length) {
                        $checkbox.prop('checked', false);
                        // Trigger change to save the checkbox state
                        $checkbox.trigger('change');
                    }
                    apiAssignedRoles[role] = false;
                } else {
                    console.error('Error removing volunteer:', response.data);
                    alert('Error removing volunteer assignment');
                }
            },
            error: function(xhr, status, err) {
                console.error('AJAX error:', err, xhr.responseText);
                alert('Error removing volunteer: ' + err);
            }
        });
    }
    
    /**
     * Helper to select a volunteer by ID in a dropdown
     */
    function selectVolunteerById(role, personId) {
        var $dropdown = $('.volunteer-dropdown[data-role="' + role + '"]');
        var found = false;
        
        // Search through dropdown options to find matching ID
        $dropdown.find('option').each(function() {
            if ($(this).val() === personId) {
                $dropdown.val(personId);
                apiAssignedRoles[role] = true; // Mark as API-assigned
                found = true;
                return false; // break
            }
        });
        
        if (!found && membersCache.length > 0) {
            // If dropdown options don't exist yet, wait a moment and retry
            setTimeout(function() {
                selectVolunteerById(role, personId);
            }, 100);
        }
    }
    
    /**
     * Helper to select a volunteer by name in a dropdown
     */
    function selectVolunteerByName(role, volunteerName) {
        var $dropdown = $('.volunteer-dropdown[data-role="' + role + '"]');
        var found = false;
        
        // Search through dropdown options to find matching name
        $dropdown.find('option').each(function() {
            if ($(this).text() === volunteerName) {
                $dropdown.val($(this).val());
                apiAssignedRoles[role] = true; // Mark as API-assigned
                found = true;
                return false; // break
            }
        });
        
        if (!found && membersCache.length > 0) {
            // If dropdown options don't exist yet, wait a moment and retry
            setTimeout(function() {
                selectVolunteerByName(role, volunteerName);
            }, 100);
        }
    }
    
    /**
     * Load current checklist status on page load
     */
    function loadStatus() {
        var currentOrigin = window.location.origin;
        var ajaxUrl = currentOrigin + '/wp-admin/admin-ajax.php';
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'kcg_checklist_get_status',
                nonce: kcgChecklist.nonce,
            },
            success: function(response) {
                if (response.success) {
                    weekData = response.data.data;
                    
                    // Populate dropdowns first
                    populateDropdowns();
                    
                    // Update checkboxes based on current data
                    $.each(weekData, function(itemId, valueOrName) {
                        var $checkbox = $('[data-item-id="' + itemId + '"]');
                        
                        // Check if it's a boolean (checkbox state)
                        if (typeof valueOrName === 'boolean') {
                            // It's a checkbox state
                            if (valueOrName) {
                                $checkbox.prop('checked', true);
                            } else {
                                $checkbox.prop('checked', false);
                            }
                        }
                    });
                    
                    // Restore saved volunteer selections
                    restoreSavedSelections();
                }
            }
        });
    }
});