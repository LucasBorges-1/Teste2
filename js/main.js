(function () {
  const track = document.querySelector('.carousel-track');
  const prevArrow = document.querySelector('.carousel-arrow');
  if (track && prevArrow) {
    prevArrow.addEventListener('click', () => {
      const card = track.querySelector(':scope > *');
      const gap = 24;
      const width = card ? card.getBoundingClientRect().width + gap : 300;
      track.scrollBy({ left: width, behavior: 'smooth' });
      if (track.scrollLeft + track.clientWidth >= track.scrollWidth - 10) {
        setTimeout(() => track.scrollTo({ left: 0, behavior: 'smooth' }), 500);
      }
    });
  }

  const form = document.querySelector('.contato-form');
  if (form) {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      let valid = true;

      const fields = {
        nome: { el: form.querySelector('#nome'), test: (v) => v.trim().length > 1 },
        email: { el: form.querySelector('#email'), test: (v) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) },
        telefone: { el: form.querySelector('#telefone'), test: (v) => v.trim().length >= 8 },
        mensagem: { el: form.querySelector('#mensagem'), test: (v) => v.trim().length > 4 },
      };

      Object.values(fields).forEach(({ el, test }) => {
        if (!el) return;
        const wrapper = el.closest('.form-field');
        if (!test(el.value)) {
          wrapper.classList.add('error');
          valid = false;
        } else {
          wrapper.classList.remove('error');
        }
      });

      const successMsg = form.querySelector('.form-success');
      if (valid) {
        successMsg.style.display = 'block';
        successMsg.textContent = 'Mensagem enviada com sucesso! Em breve entraremos em contato.';
        form.reset();
        setTimeout(() => { successMsg.style.display = 'none'; }, 5000);
      } else if (successMsg) {
        successMsg.style.display = 'none';
      }
    });
  }
})();
