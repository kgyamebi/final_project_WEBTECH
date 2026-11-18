(function () {
  document.querySelectorAll("[data-suggest]").forEach(function (input) {
    var list = document.getElementById(input.getAttribute("data-suggest"));
    if (!list) return;
    var timer = null;
    input.addEventListener("input", function () {
      clearTimeout(timer);
      var q = input.value.trim();
      if (q.length < 2) {
        list.innerHTML = "";
        return;
      }
      timer = setTimeout(function () {
        fetch("/api/places?q=" + encodeURIComponent(q))
          .then(function (r) { return r.json(); })
          .then(function (body) {
            list.innerHTML = "";
            (body.data || []).forEach(function (place) {
              var li = document.createElement("li");
              var button = document.createElement("button");
              button.type = "button";
              button.textContent = place.label || place.name;
              button.addEventListener("click", function () {
                input.value = place.name || place.label;
                list.innerHTML = "";
                input.focus();
              });
              li.appendChild(button);
              list.appendChild(li);
            });
          })
          .catch(function () {
            list.innerHTML = "";
          });
      }, 250);
    });
  });
})();
