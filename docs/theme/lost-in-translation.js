/*
 * Copyright (c) anno Domini nostri Jesu Christi MMXXVI John Boehr & contributors
 *
 * SPDX-License-Identifier: AGPL-3.0-only WITH romic-exception
 *
 * Adapted from Yumemi's mdBook navigation:
 * https://github.com/jbboehr/yumemi.php/blob/master/docs/theme/yumemi.js
 */

/* global path_to_root */

(function () {
    "use strict";

    // mdBook supplies section links for the active page. Add them to inactive pages so the full outline stays open.
    const headingsByChapter = {
        "getting-started.html": [
            { id: "installation", title: "Installation" },
            { id: "compatibility", title: "Compatibility" },
            { id: "supported-translation-apis", title: "Supported translation APIs" },
            { id: "type-inference", title: "Type inference" },
            { id: "adoption", title: "Adoption" },
        ],
        "translation-keys.html": [
            { id: "missing-translations", title: "Missing translations" },
            { id: "missing-base-locale-translations", title: "Missing base-locale translations" },
            { id: "unused-translations", title: "Unused translations" },
            { id: "dynamic-translations", title: "Dynamic translations" },
        ],
        "replacements-and-choices.html": [
            { id: "replacements", title: "Replacements" },
            { id: "choice-syntax-and-coverage", title: "Choice syntax and coverage" },
            { id: "complete-plural-forms", title: "Complete plural forms" },
        ],
        "locales-and-files.html": [
            { id: "locale-validation", title: "Locale validation" },
            { id: "invalid-character-encoding", title: "Invalid character encoding" },
            { id: "translation-file-errors", title: "Translation-file errors" },
            { id: "supported-layouts", title: "Supported layouts" },
        ],
    };

    function createHeadingList(pageUrl, headings) {
        const list = document.createElement("ol");
        list.classList.add("section");

        for (const heading of headings) {
            const item = document.createElement("li");
            item.classList.add("header-item");

            const wrapper = document.createElement("span");
            wrapper.classList.add("chapter-link-wrapper");

            const link = document.createElement("a");
            link.href = `${pageUrl}#${heading.id}`;
            link.textContent = heading.title;

            wrapper.append(link);
            item.append(wrapper);
            list.append(item);
        }

        return list;
    }

    function mountWideNavigation() {
        const navigation = document.querySelector(".nav-wide-wrapper");
        const title = document.querySelector("#mdbook-menu-bar .menu-title");

        if (!navigation || !title || navigation.querySelector(".lit-navigation-title")) {
            return;
        }

        const previous = navigation.querySelector(".nav-chapters.previous");
        const next = navigation.querySelector(".nav-chapters.next");

        if (!previous) {
            const placeholder = document.createElement("span");
            placeholder.classList.add("lit-navigation-placeholder");
            navigation.prepend(placeholder);
        }

        const navigationTitle = title.cloneNode(true);
        navigationTitle.classList.remove("menu-title");
        navigationTitle.classList.add("lit-navigation-title");
        navigation.insertBefore(navigationTitle, next);

        if (!next) {
            const placeholder = document.createElement("span");
            placeholder.classList.add("lit-navigation-placeholder");
            navigation.append(placeholder);
        }

        document.documentElement.classList.add("lit-wide-navigation-mounted");
    }

    document.addEventListener("DOMContentLoaded", function () {
        mountWideNavigation();

        const chapterLinks = document.querySelectorAll("#mdbook-sidebar .chapter-item > .chapter-link-wrapper > a");

        for (const [chapterPath, headings] of Object.entries(headingsByChapter)) {
            const pageUrl = new URL(path_to_root + chapterPath, document.location.href);
            const chapterLink = Array.from(chapterLinks).find(function (link) {
                return new URL(link.href, document.location.href).href === pageUrl.href;
            });

            if (!chapterLink || chapterLink.classList.contains("active")) {
                continue;
            }

            const container = document.createElement("div");
            container.classList.add("lit-page-outline");
            container.append(createHeadingList(pageUrl.href, headings));
            chapterLink.parentElement.after(container);
        }
    });
})();
