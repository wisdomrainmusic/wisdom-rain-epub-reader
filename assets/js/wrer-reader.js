document.addEventListener("DOMContentLoaded", () => {
  const selector = document.getElementById("wrer-language");
  if (!selector) return;

  selector.addEventListener("change", e => {
    const selected = e.target.value;
    document.querySelectorAll(".wrer-book").forEach(book => {
      const lang = book.getAttribute("data-language");
      book.style.display = (selected === "all" || selected === lang) ? "block" : "none";
    });
    localStorage.setItem("wrer_selected_lang", selected);
  });

  const saved = localStorage.getItem("wrer_selected_lang");
  if (saved && selector.querySelector(`option[value="${saved}"]`)) {
    selector.value = saved;
    selector.dispatchEvent(new Event("change"));
  }
});
