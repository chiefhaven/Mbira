
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

document.querySelectorAll(".hero-nav__item--with-dropdown > [role='button']").forEach(function(trigger) {
    trigger.addEventListener("keydown", function(event) {
        if (event.key === "Enter" || event.key === " ") {
            event.preventDefault();
            trigger.parentElement.classList.toggle("hero-nav__item--show-dropdown");
        }
    });
});

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
    heroMenu.classList.remove("ft-menu--js-show");
    document.querySelector("[open-nav-menu]").setAttribute("aria-expanded", "false");
    bodyScrollLock.unlock(heroMenu);
    }
});

/* Toggle Menu on mobile */
var heroMenu = document.querySelector("#hero-menu");
var openNavButton = document.querySelector("[open-nav-menu]");
function closeMobileMenu() {
    heroMenu.classList.remove("ft-menu--js-show");
    openNavButton.setAttribute("aria-expanded", "false");
    bodyScrollLock.unlock(heroMenu);
    openNavButton.focus();
}
document.querySelector("[close-nav-menu]").onclick = function(e) {
    closeMobileMenu();
};

openNavButton.onclick = function(e) {
    heroMenu.classList.add("ft-menu--js-show");
    heroMenu.style.height = window.innerHeight + "px";
    openNavButton.setAttribute("aria-expanded", "true");
    bodyScrollLock.lock(heroMenu);
    heroMenu.querySelector("a").focus();
};
document.addEventListener("keydown", function(event) {
    if (event.key === "Escape" && heroMenu.classList.contains("ft-menu--js-show")) {
        closeMobileMenu();
    }
});
/* Close mobile Menu & scroll smoothly */
function closeMenuAndGoTo(query) {
    if (heroMenu.classList.contains("ft-menu--js-show")) {
        closeMobileMenu();
    }
    setTimeout(() => {
    const element = document.getElementById(query.slice(1));
    if (!element) {
        return;
    }
    window.scrollTo({
        top: element.getBoundingClientRect().top + window.scrollY,
        behavior: window.matchMedia("(prefers-reduced-motion: reduce)").matches ? "auto" : "smooth",
    });
    }, 250);
}

document
    .querySelector("#hero-menu")
    .querySelectorAll("[href^='#']")
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
