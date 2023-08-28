
/* Hero Menu & Dropdown's JavaScript */
var showDropdown = function(e) {
    e.currentTarget.classList.add("hero-nav__item--show-dropdown");
};

var hideDropdown = function(e) {
    e.currentTarget.classList.remove("hero-nav__item--show-dropdown");
};

var toggleDropdown = function(e) {
    e.currentTarget.classList.toggle("hero-nav__item--show-dropdown");
};

function setupDropdowns(mediaQuery) {
    document
    .querySelectorAll(".hero-nav__item--with-dropdown")
    .forEach(function(liElement) {
        if (mediaQuery.matches) {
        var liElclassList = liElement.classList;
        if (
            !liElclassList.contains("hero-nav__item--dropdown-left") ||
            !liElclassList.contains("hero-nav__item--dropdown-right")
        ) {
            var linkWidth = liElement.getBoundingClientRect().width;
            liElement.querySelector(".dropdown").style.transform =
            "translateX(calc(-50% + " + linkWidth / 2 + "px))";
        }

        liElement.addEventListener("mouseenter", showDropdown);
        liElement.addEventListener("mouseleave", hideDropdown);

        /* If someone resize his browser to small screen */
        var heroNavLink = liElement.querySelector(".hero-nav__link");
        heroNavLink.removeEventListener("click", toggleDropdown);
        } else {
        /* if someone resize his browser to large screen */
        liElement.removeEventListener("mouseenter", showDropdown);
        liElement.removeEventListener("mouseleave", hideDropdown);
        liElement.querySelector(".dropdown").style.transform = "";

        liElement.addEventListener("click", toggleDropdown);
        }
    });
}

var mediaQuery = window.matchMedia("(min-width: 992px)");
/* setup dropdowns on page load */
setupDropdowns(mediaQuery);
mediaQuery.addListener(function(mediaQuery) {
    setupDropdowns(mediaQuery);

    if (mediaQuery.matches) {
    var heroMenu = document.querySelector("#hero-menu");
    heroMenu.style.height = "";
    bodyScrollLock.unlock(heroMenu);
    }
});

/* Toggle Menu on mobile */
var heroMenu = document.querySelector("#hero-menu");
document.querySelector("[close-nav-menu]").onclick = function(e) {
    heroMenu.classList.toggle("ft-menu--js-show");
    bodyScrollLock.unlock(heroMenu);
};

document.querySelector("[open-nav-menu]").onclick = function(e) {
    heroMenu.classList.toggle("ft-menu--js-show");
    heroMenu.style.height = window.innerHeight + "px";
    bodyScrollLock.lock(heroMenu);
};
/* Close mobile Menu & scroll smoothly */
function closeMenuAndGoTo(query) {
    document.querySelector("#hero-menu").classList.toggle("ft-menu--js-show");
    setTimeout(() => {
    const element = document.querySelector(query);
    window.scrollTo({
        top: element.getBoundingClientRect().top,
        behavior: "smooth",
    });
    }, 250);
}

document
    .querySelector("#hero-menu")
    .querySelectorAll("[href*='#']")
    .forEach(function(link) {
    link.onclick = function(event) {
        event.preventDefault();
        closeMenuAndGoTo(link.getAttribute("href"));
    };
    });

/* Make the navigation sticky */
var nav = document.querySelector(".hero-nav");
window.onscroll = function(e) {
    var logoElement = document.querySelector("[change-src-onscroll]");
    if (window.scrollY > nav.getBoundingClientRect().height) {
    if (!nav.classList.contains("hero-nav--is-sticky")) {
        window.logoSrc = logoElement.getAttribute("src");
        logoElement.setAttribute(
        "src",
        logoElement.getAttribute("change-src-onscroll")
        );
        logoElement.setAttribute("change-src-onscroll", logoSrc);
    }

    nav.classList.add("hero-nav--is-sticky");
    } else if (window.scrollY === 0) {
    if (nav.classList.contains("hero-nav--is-sticky")) {
        window.logoSrc = logoElement.getAttribute("src");
        logoElement.setAttribute(
        "src",
        logoElement.getAttribute("change-src-onscroll")
        );
        logoElement.setAttribute("change-src-onscroll", logoSrc);
    }

    nav.classList.remove("hero-nav--is-sticky");
    }
};

    new Splide("#unique-id-2", {
      type: "loop",
      perPage: 3,
      focus: "center",
      start: 1,
      direction: "ttb",
      height: document.querySelector("#unique-id-2").getBoundingClientRect().height + "px",
      breakpoints: {
        800: {
          perPage: 2.5,
          drag: !1,
          keyboard: !1
        },
        480: {
          perPage: 1.5,
          drag: !1,
          keyboard: !1
        }
      },
      pagination: !1
    }).mount();