const texts = [
  ["Building", "God's", "church"],
  ["with", "God's", "word"],
  ["for", "God's", "glory"],
];

let currentSet = 0;

function initTaglineAnimation() {
  const taglineDiv = document.querySelector('.tagline');
  
  if (!taglineDiv) {
    console.warn('Tagline element not found. Make sure you have a .tagline element in your HTML.');
    return;
  }
  
  const paragraphs = taglineDiv.querySelectorAll('p');
  
  if (paragraphs.length < 3) {
    console.warn('Expected 3 paragraph elements in .tagline, found:', paragraphs.length);
    return;
  }
  
  const [line1, line2, line3] = paragraphs;

  function animateText(line, newText) {
    gsap.to(line, {
      opacity: 0,
      scale: 0.5,
      rotationY: 180,
      duration: 0.25,
      onComplete: () => {
        line.textContent = newText;
        gsap.to(line, {
          opacity: 1,
          scale: 1,
          rotationY: 0,
          duration: 0.25,
        });
      },
    });
  }

  function updateText() {
    const nextSet = (currentSet + 1) % texts.length;

    if (texts[currentSet][0] !== texts[nextSet][0]) {
      animateText(line1, texts[nextSet][0]);
    }
    if (texts[currentSet][1] !== texts[nextSet][1]) {
      animateText(line2, texts[nextSet][1]);
    }
    if (texts[currentSet][2] !== texts[nextSet][2]) {
      animateText(line3, texts[nextSet][2]);
    }

    currentSet = nextSet;
  }

  // Initialize with first set of text
  line1.textContent = texts[0][0];
  line2.textContent = texts[0][1];
  line3.textContent = texts[0][2];

  // Start the animation cycle
  setInterval(updateText, 3000);
}

// Wait for DOM to be ready and GSAP to be loaded
document.addEventListener('DOMContentLoaded', function() {
  if (typeof gsap !== 'undefined') {
    initTaglineAnimation();
  } else {
    console.error('GSAP library not loaded. Animation will not work.');
  }
});
