document.addEventListener("DOMContentLoaded", () => {
  const header = document.querySelector(".header-inner");
  const logo = document.querySelector(".site-logo");
  const logoLight = document.getElementById("lottie-logo-light");
  const logoNormal = document.getElementById("lottie-logo-normal");

  const themePath = window.location.origin + "/wp-content/themes/kcg-wp-theme-dev";
  const lightAnimationPath = themePath + "/assets/anim/logo_kcg_unified_animation_light.json";
  const normalAnimationPath = themePath + "/assets/anim/logo_kcg_unified_animation.json";
  const segmentStart = 186;
  const segmentEnd = 270;
  const threshold = (20 * window.innerHeight) / 100;
  let isScrolled = window.scrollY > threshold;
  let isAnimating = false;
  let ticking = false;
  let lightAnimation;
  let normalAnimation;
  let lightLoaded = false;
  let normalLoaded = false;

  const createAnimation = (container, path, onLoad) => {
    const anim = lottie.loadAnimation({
      container,
      renderer: "svg",
      loop: false,
      autoplay: false,
      path,
    });
    anim.setSpeed(1);
    anim.addEventListener("DOMLoaded", () => onLoad(anim));
    anim.addEventListener("complete", () => {
      if (isAnimating) {
        isAnimating = false;
      }
    });
    return anim;
  };

  const initializeLightAnimation = () => {
    lightAnimation = createAnimation(logoLight, lightAnimationPath, (anim) => {
      lightLoaded = true;
      anim.goToAndStop(segmentStart - 1, true);
    });
  };

  const initializeNormalAnimation = () => {
    normalAnimation = createAnimation(logoNormal, normalAnimationPath, (anim) => {
      normalLoaded = true;
      anim.goToAndStop(isScrolled ? segmentEnd - 1 : segmentStart - 1, true);
    });
  };

  const switchToNormal = () => {
    if (!normalLoaded) {
      return;
    }
    logoLight.style.opacity = "0";
    logoNormal.style.opacity = "1";
    normalAnimation.goToAndStop(segmentStart - 1, true);
    normalAnimation.playSegments([segmentStart, segmentEnd], true);
    isAnimating = true;
  };

  const switchToLight = () => {
    if (!lightLoaded) {
      return;
    }
    logoLight.style.opacity = "1";
    logoNormal.style.opacity = "0";
    lightAnimation.goToAndStop(segmentStart - 1, true);
    lightAnimation.playSegments([segmentEnd, segmentStart], true);
    isAnimating = true;
  };

  const updateHeader = () => {
    const currentY = window.scrollY;
    const shouldBeScrolled = currentY > threshold;
    const shouldToggle = isScrolled ? currentY < threshold : currentY > threshold;

    if (shouldToggle && !isAnimating) {
      if (!isScrolled && shouldBeScrolled) {
        header.classList.add("scrolled");
        switchToNormal();
        isScrolled = true;
      } else if (isScrolled && !shouldBeScrolled) {
        header.classList.remove("scrolled");
        switchToLight();
        isScrolled = false;
      }
    }
  };

  initializeLightAnimation();
  initializeNormalAnimation();

  // Make logo clickable to navigate to home page
  logo.addEventListener("click", () => {
    window.location.href = window.location.origin;
  });

  // Add cursor pointer style to indicate it's clickable
  logo.style.cursor = "pointer";

  if (isScrolled) {
    header.classList.add("scrolled");
    logoLight.style.opacity = "0";
    logoNormal.style.opacity = "1";
  } else {
    logoLight.style.opacity = "1";
    logoNormal.style.opacity = "0";
  }

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
