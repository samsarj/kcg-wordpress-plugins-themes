document.addEventListener("DOMContentLoaded", () => {
  const header = document.querySelector(".header-inner");
  const logo = document.getElementById("lottie-logo");

  const themePath = window.location.origin + "/wp-content/themes/kcg-wp-theme";
  const animationPath = themePath + "/assets/anim/logo_kcg_unified_animation.json";
  const segmentStart = 186;
  const segmentEnd = 270;
  
  const animation = lottie.loadAnimation({
    container: logo,
    renderer: "svg",
    loop: false,
    autoplay: false,
    path: animationPath,
  });

  animation.addEventListener("DOMLoaded", () => {
    // Set initial frame based on scroll position
    const threshold = (20 * window.innerHeight) / 100;
    if (window.scrollY > threshold) {
      animation.goToAndStop(segmentEnd - 1, true);
    } else {
      animation.goToAndStop(segmentStart - 1, true);
    }
  });

  animation.addEventListener("complete", () => {
    if (isAnimating) {
      isAnimating = false;
    }
  });

  // Make logo clickable to navigate to home page
  logo.addEventListener("click", () => {
    window.location.href = window.location.origin;
  });

  // Add cursor pointer style to indicate it's clickable
  logo.style.cursor = "pointer";

  const threshold = (20 * window.innerHeight) / 100;
  let isScrolled = window.scrollY > threshold;
  let isAnimating = false;
  let ticking = false;

  // Apply initial state based on scroll position
  if (isScrolled) {
    header.classList.add("scrolled");
  }

  const playSegment = (forward) => {
    const segment = forward ? [segmentStart, segmentEnd] : [segmentEnd, segmentStart];
    isAnimating = true;
    animation.playSegments(segment, true);
  };

  const updateHeader = () => {
    const currentY = window.scrollY;
    const shouldBeScrolled = currentY > threshold;
    const shouldToggle = isScrolled ? currentY < threshold : currentY > threshold;

    if (shouldToggle && !isAnimating) {
      if (!isScrolled && shouldBeScrolled) {
        header.classList.add("scrolled");
        playSegment(true);
        isScrolled = true;
      } else if (isScrolled && !shouldBeScrolled) {
        header.classList.remove("scrolled");
        playSegment(false);
        isScrolled = false;
      }
    }
  };

  const requestTick = () => {
    if (!ticking) {
      requestAnimationFrame(() => {
        updateHeader();
        ticking = false;
      });
      ticking = true;
    }
  };

  window.addEventListener("scroll", requestTick);
  
  // Check initial scroll position on page load
  updateHeader();
});
