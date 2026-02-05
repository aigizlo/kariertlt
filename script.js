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
const modalCta = document.querySelector('[data-fill-material]');
const materialSelect = document.querySelector('select[name="material"]');
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

if (callButton) {
    const footer = document.querySelector('.footer');
    if (footer && 'IntersectionObserver' in window) {
        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        callButton.classList.add('is-hidden-footer');
                    } else {
                        callButton.classList.remove('is-hidden-footer');
                    }
                });
            },
            { rootMargin: '0px 0px -10% 0px', threshold: 0 }
        );
        observer.observe(footer);
    }
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

let lastProductTitle = '';
const products = document.querySelectorAll('.product');
products.forEach((product) => {
    product.addEventListener('click', () => {
        const { title, desc, gost, spec, use, image } = product.dataset;
        lastProductTitle = title || '';
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

if (modalCta) {
    modalCta.addEventListener('click', () => {
        closeModal();
        if (materialSelect && lastProductTitle) {
            const option = Array.from(materialSelect.options).find(
                (opt) => opt.textContent.trim() === lastProductTitle
            );
            if (option) {
                materialSelect.value = option.value;
            }
        }
    });
}

if (modal) {
    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });
}

const initGalleryFilters = () => {
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
};

initGalleryFilters();

const phoneInput = document.querySelector('input[name="phone"]');
if (phoneInput) {
    phoneInput.addEventListener('input', (event) => {
        let digits = phoneInput.value.replace(/\D/g, '');
        if (digits.startsWith('7')) {
            digits = digits.slice(1);
        }
        if (event.inputType === 'deleteContentBackward') {
            const raw = phoneInput.value;
            if (raw && /[^0-9]$/.test(raw)) {
                digits = digits.slice(0, -1);
            }
        }
        digits = digits.slice(0, 10);
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

const renderFaq = (items) => {
    const list = document.querySelector('.faq__list');
    if (!list || !Array.isArray(items)) return;
    list.innerHTML = '';
    items.forEach((item) => {
        if (!item.question) return;
        const details = document.createElement('details');
        const summary = document.createElement('summary');
        summary.textContent = item.question;
        const p = document.createElement('p');
        p.textContent = item.answer || '';
        details.appendChild(summary);
        details.appendChild(p);
        list.appendChild(details);
    });
};

const renderGallery = (items) => {
    const grid = document.querySelector('.gallery__grid');
    if (!grid || !Array.isArray(items)) return;
    grid.innerHTML = '';
    items.forEach((item) => {
        if (!item.image) return;
        const figure = document.createElement('figure');
        figure.className = 'gallery__item';
        figure.dataset.category = item.category || 'all';
        const picture = document.createElement('picture');
        const isWebp = /\.webp$/i.test(item.image);
        if (isWebp) {
            const source = document.createElement('source');
            source.srcset = item.image;
            source.type = 'image/webp';
            picture.appendChild(source);
        }
        const img = document.createElement('img');
        img.src = item.image;
        img.alt = item.alt || '';
        img.loading = 'lazy';
        img.width = 320;
        img.height = 200;
        img.addEventListener('error', () => {
            if (/\.webp$/i.test(img.src)) {
                img.src = img.src.replace(/\.webp$/i, '.png');
                return;
            }
            if (/\.png$/i.test(img.src)) {
                img.src = img.src.replace(/\.png$/i, '.jpg');
                return;
            }
            if (/\.jpg$/i.test(img.src)) {
                img.src = img.src.replace(/\.jpg$/i, '.jpeg');
            }
        });
        picture.appendChild(img);
        figure.appendChild(picture);
        grid.appendChild(figure);
    });
    initGalleryFilters();
};

const renderDocuments = (items) => {
    const list = document.querySelector('.documents__list');
    if (!list || !Array.isArray(items)) return;
    list.innerHTML = '';
    items.forEach((item) => {
        if (!item.title) return;
        const link = document.createElement('a');
        link.className = 'doc';
        link.href = item.url || '#';
        link.textContent = item.title;
        list.appendChild(link);
    });
};

const loadContentFromJson = async () => {
    try {
        const [faqRes, galleryRes, docsRes] = await Promise.all([
            fetch('content/faq.json'),
            fetch('content/gallery.json'),
            fetch('content/documents.json')
        ]);
        if (faqRes.ok) {
            const faqData = await faqRes.json();
            renderFaq(faqData.items || []);
        }
        if (galleryRes.ok) {
            const galleryData = await galleryRes.json();
            renderGallery(galleryData.items || []);
        }
        if (docsRes.ok) {
            const docsData = await docsRes.json();
            renderDocuments(docsData.items || []);
        }
    } catch (error) {
        console.warn('Не удалось загрузить данные из JSON', error);
    }
};

loadContentFromJson();
