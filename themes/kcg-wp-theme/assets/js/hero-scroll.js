document.addEventListener('DOMContentLoaded', function() {
  const arrow = document.querySelector('.hero-scroll-arrow');
  if (!arrow) {
    return;
  }

  arrow.addEventListener('click', function(event) {
    event.preventDefault();
    window.scrollTo({
      top: Math.round(window.innerHeight * 0.9),
      left: 0,
      behavior: 'smooth',
    });
  });

  const cover = document.querySelector('.hero-cover');
  const wrapper = document.querySelector('.hero-scroll-arrow-wrapper');
  if (cover && wrapper && !cover.contains(wrapper)) {
    cover.appendChild(wrapper);
  }
});
