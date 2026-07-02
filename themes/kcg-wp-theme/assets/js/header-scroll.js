document.addEventListener("DOMContentLoaded", () => {
  const header = document.querySelector(".header-inner");
  const logo = document.querySelector(".site-logo");
  const logoLight = document.getElementById("lottie-logo-light");
  const logoNormal = document.getElementById("lottie-logo-normal");

  // const currentScript =
  //   document.currentScript ||
  //   document.querySelector('script[src*="header-scroll.js"]');
  // const scriptUrl = currentScript
  //   ? currentScript.src
  //   : window.location.origin +
  //     "/wp-content/themes/kcg-wp-theme/assets/js/header-scroll.js";
  // const lightAnimationPath = new URL(
  //   "../anim/logo_kcg_unified_animation_light.json",
  //   scriptUrl,
  // ).href;
  // const normalAnimationPath = new URL(
  //   "../anim/logo_kcg_unified_animation.json",
  //   scriptUrl,
  // ).href;

  const lightAnimationPath = window.location.origin + "/wp-content/themes/kcg-wp-theme/assets/anim/logo_kcg_unified_animation_light.json";
  const normalAnimationPath = window.location.origin + "/wp-content/themes/kcg-wp-theme/assets/anim/logo_kcg_unified_animation.json";

  if (
    !header ||
    !logo ||
    !logoLight ||
    !logoNormal ||
    typeof window.lottie === "undefined"
  ) {
    console.warn(
      "Header scroll script aborted: missing DOM elements or lottie.",
    );
    return;
  }

  const setLogoReady = () => {
    if (lightLoaded || normalLoaded) {
      logo.classList.add("ready");
    }
  };
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
      rendererSettings: {
        preserveAspectRatio: "xMinYMid meet",
      },
    });
    anim.addEventListener("DOMLoaded", () => {
      const segmentFrames = segmentEnd - segmentStart;
      const fps = anim.animationData.fr;
      anim.setSpeed(segmentFrames / fps);
      onLoad(anim);
    });
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
      setLogoReady();
    });
  };

  const initializeNormalAnimation = () => {
    normalAnimation = createAnimation(
      logoNormal,
      normalAnimationPath,
      (anim) => {
        normalLoaded = true;
        anim.goToAndStop(segmentStart - 1, true);
        setLogoReady();
      },
    );
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

    if (shouldBeScrolled && !isScrolled) {
      header.classList.add("scrolled");
      switchToNormal();
      isScrolled = true;
    } else if (!shouldBeScrolled && isScrolled) {
      header.classList.remove("scrolled");
      switchToLight();
      isScrolled = false;
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
