const header = document.querySelector('.header');
const intro = document.querySelector('.intro');
const introScroll = document.querySelector('.intro__scroll');
const callButton = document.querySelector('.call');
const burger = document.querySelector('.burger');
const nav = document.querySelector('.nav');
const modal = document.querySelector('.modal');
const modalClose = document.querySelector('.modal__close');
const modalTitle = document.getElementById('modal-title');
const modalDesc = document.querySelector('.modal__desc');
const modalImage = document.querySelector('.modal__image');
const modalFields = {
    spec: document.querySelector('[data-field="spec"]'),
    gost: document.querySelector('[data-field="gost"]'),
    use: document.querySelector('[data-field="use"]')
};

if (burger && nav) {
    burger.addEventListener('click', () => {
        burger.classList.toggle('is-active');
        nav.classList.toggle('is-open');
        const isOpen = nav.classList.contains('is-open');
        burger.setAttribute('aria-expanded', String(isOpen));
        document.body.style.overflow = isOpen ? 'hidden' : '';
    });

    document.addEventListener('click', (event) => {
        if (!nav.classList.contains('is-open')) return;
        const target = event.target;
        if (target instanceof Element && !nav.contains(target) && !burger.contains(target)) {
            nav.classList.remove('is-open');
            burger.classList.remove('is-active');
            document.body.style.overflow = '';
        }
    });

    nav.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            nav.classList.remove('is-open');
            burger.classList.remove('is-active');
            document.body.style.overflow = '';
        });
    });
}

if (introScroll) {
    introScroll.addEventListener('click', () => {
        const hero = document.getElementById('hero');
        if (hero) {
            hero.scrollIntoView({ behavior: 'smooth' });
        }
    });
}

if (header && intro) {
    const toggleHeader = () => {
        const triggerPoint = intro.offsetHeight - 80;
        if (window.scrollY > triggerPoint) {
            header.classList.add('is-visible');
            if (callButton) callButton.classList.add('is-visible');
        } else {
            header.classList.remove('is-visible');
            if (callButton) callButton.classList.remove('is-visible');
        }
    };
    toggleHeader();
    window.addEventListener('scroll', toggleHeader);
}

const productsTrack = document.querySelector('.products__track');
const prevBtn = document.querySelector('.control--prev');
const nextBtn = document.querySelector('.control--next');

if (productsTrack && prevBtn && nextBtn) {
    const scrollAmount = () => productsTrack.querySelector('.product')?.offsetWidth || 260;

    prevBtn.addEventListener('click', () => {
        productsTrack.scrollBy({ left: -scrollAmount(), behavior: 'smooth' });
    });

    nextBtn.addEventListener('click', () => {
        productsTrack.scrollBy({ left: scrollAmount(), behavior: 'smooth' });
    });

    let autoScroll = setInterval(() => {
        const maxScroll = productsTrack.scrollWidth - productsTrack.clientWidth;
        const next = productsTrack.scrollLeft + scrollAmount();
        if (next >= maxScroll - 10) {
            productsTrack.scrollTo({ left: 0, behavior: 'smooth' });
        } else {
            productsTrack.scrollBy({ left: scrollAmount(), behavior: 'smooth' });
        }
    }, 3500);

    productsTrack.addEventListener('mouseenter', () => clearInterval(autoScroll));
    productsTrack.addEventListener('mouseleave', () => {
        autoScroll = setInterval(() => {
            const maxScroll = productsTrack.scrollWidth - productsTrack.clientWidth;
            const next = productsTrack.scrollLeft + scrollAmount();
            if (next >= maxScroll - 10) {
                productsTrack.scrollTo({ left: 0, behavior: 'smooth' });
            } else {
                productsTrack.scrollBy({ left: scrollAmount(), behavior: 'smooth' });
            }
        }, 3500);
    });
}

const products = document.querySelectorAll('.product');
products.forEach((product) => {
    product.addEventListener('click', () => {
        const { title, desc, gost, spec, use, image } = product.dataset;
        modalTitle.textContent = title;
        modalDesc.textContent = desc;
        modalFields.spec.textContent = spec;
        modalFields.gost.textContent = gost;
        modalFields.use.textContent = use;
        modalImage.src = image;
        modalImage.alt = title;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    });
});

const closeModal = () => {
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
};

if (modalClose) {
    modalClose.addEventListener('click', closeModal);
}

if (modal) {
    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });
}

const filters = document.querySelectorAll('.filter');
const galleryItems = document.querySelectorAll('.gallery__item');

filters.forEach((filter) => {
    filter.addEventListener('click', () => {
        filters.forEach((btn) => btn.classList.remove('is-active'));
        filter.classList.add('is-active');
        const value = filter.dataset.filter;
        galleryItems.forEach((item) => {
            const match = value === 'all' || item.dataset.category === value;
            item.style.display = match ? 'block' : 'none';
        });
    });
});

const phoneInput = document.querySelector('input[name="phone"]');
if (phoneInput) {
    phoneInput.addEventListener('input', () => {
        let digits = phoneInput.value.replace(/\D/g, '');
        if (digits.startsWith('7')) {
            digits = digits.slice(1);
        }
        let formatted = '+7';
        if (digits.length > 0) {
            formatted += ' (' + digits.slice(0, 3);
        }
        if (digits.length >= 3) {
            formatted += ') ' + digits.slice(3, 6);
        }
        if (digits.length >= 6) {
            formatted += '-' + digits.slice(6, 8);
        }
        if (digits.length >= 8) {
            formatted += '-' + digits.slice(8, 10);
        }
        phoneInput.value = formatted;
    });
}

const form = document.getElementById('requestForm');
if (form) {
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        form.classList.add('is-sent');
        alert('Спасибо! Мы получили заявку и скоро свяжемся с вами.');
        form.reset();
    });
}
