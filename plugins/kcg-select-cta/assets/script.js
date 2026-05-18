/**
 * KCG Select CTA - Simple Version with Letter Animation
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
            
            // Don't auto-scroll if there's only one option
            if (options.length <= 1) {
                return;
            }
            
            // Hide original select and create display
            $select.hide();
            var $display = $('<div class="kcg-text-display"></div>');
            $wrapper.append($display);
            
            // Set initial text with animation
            this.typeText($display, options.eq(0).text());
            
            // Simple interval for changing text
            var intervalId = setInterval(function() {
                if (!isPaused && !self.animating) {
                    currentIndex = (currentIndex + 1) % options.length;
                    var newText = options.eq(currentIndex).text();
                    
                    // Animate to new text
                    self.animateToNewText($display, newText);
                    
                    // Update the hidden select
                    $select.prop('selectedIndex', currentIndex);
                }
            }, speed);
            
            // Pause on hover
            $wrapper.on('mouseenter', function() {
                isPaused = true;
            }).on('mouseleave', function() {
                isPaused = false;
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
    
    // Add simple CSS
    if (!$('#kcg-simple-styles').length) {
        $('head').append(
            '<style id="kcg-simple-styles">' +
                '.kcg-text-display::after {' +
                    'content: "|";' +
                    'color: #666;' +
                    'animation: kcg-blink 1s infinite;' +
                    'margin-left: 2px;' +
                '}' +
                '@keyframes kcg-blink {' +
                    '0%, 50% { opacity: 1; }' +
                    '51%, 100% { opacity: 0; }' +
                '}' +
                '@media (max-width: 768px) {' +
                    '.kcg-text-display {' +
                        'min-width: 200px;' +
                        'font-size: 14px;' +
                    '}' +
                '}' +
            '</style>'
        );
    }
    
    // Initialize
    var kcgCTA = new KCGSelectCTA();
    window.KCGSelectCTA = kcgCTA;
});
