document.addEventListener("DOMContentLoaded", () => {
  const header = document.querySelector(".header-inner");
  const logo = document.querySelector(".site-logo");
  const logoLight = document.getElementById("lottie-logo-light");
  const logoNormal = document.getElementById("lottie-logo-normal");
  const logoFallback = document.querySelector(".site-logo-fallback");

  const currentScript = document.currentScript || document.querySelector('script[src*="/assets/js/header-scroll.js"]');

  const themeUrl = currentScript.src.replace(/\/assets\/js\/[^/]+$/, "");
  const lightAnimationPath = `${themeUrl}/assets/anim/logo_kcg_unified_animation_light.json`;
  const normalAnimationPath = `${themeUrl}/assets/anim/logo_kcg_unified_animation.json`;

  const segmentStart = 186;
  const segmentEnd = 270;
  const staticSegmentStart = segmentStart - 1;
  const staticSegmentEnd = segmentEnd - 1;
  const threshold = (20 * window.innerHeight) / 100;
  const isPageTemplate = document.body.classList.contains("page");
  let isScrolled = window.scrollY > threshold;
  let scrollStateInitialized = false;
  let ticking = false;
  let lightAnimation;
  let normalAnimation;
  let lightLoaded = false;
  let normalLoaded = false;

  const getLogoState = (scrolled = isScrolled) => {
    if (isPageTemplate && !scrolled) {
      return { showNormal: false, segment: staticSegmentStart };
    }

    return {
      showNormal: true,
      segment: scrolled ? staticSegmentEnd : staticSegmentStart,
    };
  };

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

    return anim;
  };

  const setLogoVisibility = (showNormal) => {
    logoLight.style.opacity = showNormal ? "0" : "1";
    logoNormal.style.opacity = showNormal ? "1" : "0";
  };

  const setLogoReady = (isReady) => {
    logo.classList.toggle("ready", isReady);
  };

  const initialLogoState = getLogoState(isScrolled);
  setLogoVisibility(initialLogoState.showNormal);

  const setInitialLogoSegment = () => {
    const state = getLogoState(isScrolled);
    if (state.showNormal) {
      if (!normalLoaded) {
        setLogoReady(false);
        return;
      }
      setLogoVisibility(true);
      normalAnimation.goToAndStop(state.segment, true);
    } else {
      if (!lightLoaded) {
        setLogoReady(false);
        return;
      }
      setLogoVisibility(false);
      lightAnimation.goToAndStop(state.segment, true);
    }

    setLogoReady(true);
  };

  const playSegments = (anim, from, to) => {
    anim.goToAndStop(from, true);
    anim.playSegments([from, to], true);
  };

  const switchToNormal = (playForward = true) => {
    if (!normalLoaded) {
      setLogoReady(false);
      return;
    }

    setLogoVisibility(true);
    const [from, to] = playForward
      ? [segmentStart, segmentEnd]
      : [segmentEnd, segmentStart];
    playSegments(normalAnimation, from, to);
  };

  const switchToLight = () => {
    if (!lightLoaded) {
      setLogoReady(false);
      return;
    }

    setLogoVisibility(false);
    playSegments(lightAnimation, segmentEnd, segmentStart);
  };

  const setScrolledState = (scrolled) => {
    if (!scrollStateInitialized) {
      isScrolled = scrolled;
      header.classList.toggle("scrolled", scrolled);
      setInitialLogoSegment();
      scrollStateInitialized = true;
      return;
    }

    if (scrolled === isScrolled) {
      return;
    }

    header.classList.toggle("scrolled", scrolled);

    if (isPageTemplate) {
      if (scrolled) {
        switchToNormal(true);
      } else {
        switchToLight();
      }
    } else {
      switchToNormal(scrolled);
    }

    isScrolled = scrolled;
  };

  const updateHeader = () => {
    const shouldBeScrolled = window.scrollY > threshold;
    setScrolledState(shouldBeScrolled);
  };

  const initializeLightAnimation = () => {
    lightAnimation = createAnimation(logoLight, lightAnimationPath, () => {
      lightLoaded = true;
      setInitialLogoSegment();
    });
  };

  const initializeNormalAnimation = () => {
    normalAnimation = createAnimation(logoNormal, normalAnimationPath, () => {
      normalLoaded = true;
      setInitialLogoSegment();
    });
  };

  initializeLightAnimation();
  initializeNormalAnimation();

  // Make logo clickable to navigate to home page
  logo.addEventListener("click", () => {
    window.location.href = window.location.origin;
  });

  // Add cursor pointer style to indicate it's clickable
  logo.style.cursor = "pointer";

  const scheduleUpdate = () => {
    if (!ticking) {
      requestAnimationFrame(() => {
        updateHeader();
        ticking = false;
      });
      ticking = true;
    }
  };

  window.addEventListener("scroll", scheduleUpdate);
  window.addEventListener("load", () => requestAnimationFrame(updateHeader));
  window.addEventListener("pageshow", () => requestAnimationFrame(updateHeader));

  requestAnimationFrame(updateHeader);
});
