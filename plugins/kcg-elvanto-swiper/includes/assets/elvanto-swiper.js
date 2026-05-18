document.addEventListener("DOMContentLoaded", function () {
  var swiper = new Swiper(".swiper-container", {
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
    // Use slide effect instead of cards for better button interaction
    effect: "slide",
    centeredSlides: false,
  });

  // Equalize card heights
  function equalizeCardHeights() {
    const cards = document.querySelectorAll(".event-card");
    let maxHeight = 0;

    // Reset heights first to get natural heights
    cards.forEach(card => {
      card.style.height = "auto";
    });

    // Find the tallest card
    cards.forEach(card => {
      maxHeight = Math.max(maxHeight, card.offsetHeight);
    });

    // Set all cards to the max height
    cards.forEach(card => {
      card.style.height = maxHeight + "px";
    });
  }

  // Run on load and on window resize
  equalizeCardHeights();
  window.addEventListener("resize", equalizeCardHeights);
});
