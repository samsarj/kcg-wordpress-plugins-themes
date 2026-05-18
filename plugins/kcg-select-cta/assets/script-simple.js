/**
 * KCG Select CTA - Functional Select with Animation
 */

jQuery(document).ready(function($) {
    
    function KCGSelectCTA() {
        this.animating = false;
        this.init();
    }
    
    KCGSelectCTA.prototype = {
        init: function() {
            var self = this;
            $('.kcg-auto-select').each(function() {
                self.setupSelect($(this));
            });
        },
        
        setupSelect: function($select) {
            var self = this;
            var $wrapper = $select.closest('.kcg-select-wrapper');
            var options = $select.find('option');
            var speed = parseInt($select.data('speed')) || 3000;
            var currentIndex = 0;
            var isPaused = false;
            var isUserSelected = false;
            var intervalId = null;
            
            // Don't auto-scroll if there's only one option
            if (options.length <= 1) {
                return;
            }
            
            // Hide the original select initially
            $select.hide();
            
            // Create display for animation
            var $display = $('<div class="kcg-text-display"></div>');
            $wrapper.append($display);
            
            // Add chevron
            var $chevron = $('<div class="kcg-chevron">▼</div>');
            $wrapper.append($chevron);
            
            // Set initial text with animation
            this.typeText($display, options.eq(0).text());
            
            // Start animation interval
            function startAnimation() {
                if (intervalId) clearInterval(intervalId);
                intervalId = setInterval(function() {
                    if (!isPaused && !self.animating && !isUserSelected) {
                        currentIndex = (currentIndex + 1) % options.length;
                        var newText = options.eq(currentIndex).text();
                        
                        // Animate to new text
                        self.animateToNewText($display, newText);
                        
                        // Update the select
                        $select.prop('selectedIndex', currentIndex);
                    }
                }, speed);
            }
            
            startAnimation();
            
            // Simple click to open dropdown
            $wrapper.on('click', function(e) {
                if (!isUserSelected) {
                    // Hide animation elements
                    $display.hide();
                    $chevron.hide();
                    
                    // Show and style the real select
                    $select.show().css({
                        'position': 'relative',
                        'width': '100%',
                        'background': 'white',
                        'border': '2px solid #4A90E2',
                        'border-radius': '6px',
                        'padding': '12px 16px',
                        'font-size': '16px',
                        'z-index': 10
                    });
                    
                    // Open the dropdown
                    $select[0].size = Math.min(options.length, 6);
                    $select.focus();
                }
            });
            
            // Handle selection
            $select.on('change', function() {
                isUserSelected = true;
                if (intervalId) clearInterval(intervalId);
                
                // Keep select visible and hide animation
                $(this)[0].size = 1;
                $display.hide();
                $chevron.hide();
            });
            
            // Handle blur (if user clicks away without selecting)
            $select.on('blur', function() {
                if (!isUserSelected) {
                    $(this)[0].size = 1;
                    $select.hide();
                    $display.show();
                    $chevron.show();
                    isPaused = false;
                }
            });
            
            // Pause on hover
            $wrapper.on('mouseenter', function() {
                if (!isUserSelected) isPaused = true;
            }).on('mouseleave', function() {
                if (!isUserSelected) isPaused = false;
            });
            
            // Store for cleanup
            $select.data('intervalId', intervalId);
        },
        
        animateToNewText: function($display, newText) {
            var self = this;
            this.animating = true;
            
            // First, delete current text letter by letter
            this.deleteText($display, function() {
                // Then type new text letter by letter
                self.typeText($display, newText, function() {
                    self.animating = false;
                });
            });
        },
        
        deleteText: function($display, callback) {
            var currentText = $display.text();
            var deleteSpeed = 40; // Speed of deletion (ms per character)
            var index = currentText.length;
            
            var deleteInterval = setInterval(function() {
                if (index > 0) {
                    index--;
                    $display.text(currentText.substring(0, index));
                } else {
                    clearInterval(deleteInterval);
                    if (callback) callback();
                }
            }, deleteSpeed);
        },
        
        typeText: function($display, text, callback) {
            var typeSpeed = 80; // Speed of typing (ms per character)
            var index = 0;
            
            // Clear display first
            $display.text('');
            
            var typeInterval = setInterval(function() {
                if (index < text.length) {
                    $display.text(text.substring(0, index + 1));
                    index++;
                } else {
                    clearInterval(typeInterval);
                    if (callback) callback();
                }
            }, typeSpeed);
        },
        
        pauseAll: function() {
            $('.kcg-auto-select').each(function() {
                var intervalId = $(this).data('intervalId');
                if (intervalId) {
                    clearInterval(intervalId);
                }
            });
        }
    };
    
    // Add simple CSS for functional select
    if (!$('#kcg-functional-styles').length) {
        $('head').append(
            '<style id="kcg-functional-styles">' +
                '.kcg-select-wrapper {' +
                    'position: relative;' +
                    'min-width: 150px;' +
                    'cursor: pointer;' +
                '}' +
                '.kcg-text-display {' +
                    'background: white;' +
                    'border: 1px solid #ddd;' +
                    'border-radius: 6px;' +
                    'padding: 12px 40px 12px 16px;' +
                    'font-size: 16px;' +
                    'color: #333;' +
                    'min-width: 150px;' +
                    'text-align: left;' +
                    'cursor: pointer;' +
                '}' +
                '.kcg-text-display::after {' +
                    'content: "|";' +
                    'color: #666;' +
                    'animation: kcg-blink 1s infinite;' +
                    'margin-left: 2px;' +
                '}' +
                '.kcg-chevron {' +
                    'position: absolute;' +
                    'right: 12px;' +
                    'top: 50%;' +
                    'transform: translateY(-50%);' +
                    'color: #666;' +
                    'font-size: 12px;' +
                    'cursor: pointer;' +
                '}' +
                '.kcg-select-wrapper:hover .kcg-text-display {' +
                    'border-color: #999;' +
                '}' +
                '@keyframes kcg-blink {' +
                    '0%, 50% { opacity: 1; }' +
                    '51%, 100% { opacity: 0; }' +
                '}' +
            '</style>'
        );
    }
    
    // Initialize
    var kcgCTA = new KCGSelectCTA();
    window.KCGSelectCTA = kcgCTA;
});
