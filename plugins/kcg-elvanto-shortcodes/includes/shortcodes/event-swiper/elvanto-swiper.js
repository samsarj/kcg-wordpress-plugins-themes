document.addEventListener("DOMContentLoaded", function () {
  if (typeof Swiper !== "function") {
    return;
  }

  const swipers = Array.from(document.querySelectorAll(".elvanto-swiper")).map(function (container) {
    const swiper = new Swiper(container, {
      slidesPerView: "auto",
      spaceBetween: 20,
      grabCursor: true,
      mousewheel: {
        enabled: true,
        forceToAxis: true,
      },
      keyboard: {
        enabled: true,
        onlyInViewport: true,
      },
      effect: "slide",
      centeredSlides: false,
    });

    return { container: container, swiper: swiper };
  });

  function equalizeCardHeights(container) {
    const cards = container.querySelectorAll(".swiper-slide .event-card");
    let maxHeight = 0;

    cards.forEach(function (card) {
      card.style.height = "auto";
    });

    cards.forEach(function (card) {
      maxHeight = Math.max(maxHeight, card.offsetHeight);
    });

    cards.forEach(function (card) {
      card.style.height = maxHeight ? maxHeight + "px" : "auto";
    });
  }

  function refreshSwipers() {
    swipers.forEach(function (item) {
      equalizeCardHeights(item.container);
      item.swiper.update();
    });
  }

  refreshSwipers();
  window.addEventListener("load", refreshSwipers, { once: true });
  window.addEventListener("resize", refreshSwipers);
});
