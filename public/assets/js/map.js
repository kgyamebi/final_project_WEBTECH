(function () {
  var node = document.getElementById("map");
  if (!node || typeof L === "undefined") return;

  var status = document.getElementById("map-status");
  function say(text) {
    if (status) status.textContent = text;
  }

  var map = L.map(node, { scrollWheelZoom: true });
  L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
  }).addTo(map);
  map.setView([5.603, -0.205], 12);
  say("Map ready. Tiles load from OpenStreetMap when the network is available. The list still works if tiles do not.");

  var mode = node.getAttribute("data-mode");

  function readJson(id) {
    var el = document.getElementById(id);
    if (!el) return null;
    try { return JSON.parse(el.textContent); } catch (e) { return null; }
  }

  function stopIcon() {
    return L.divIcon({ className: "", html: '<span class="map-dot stop-dot"></span>', iconSize: [14, 14], iconAnchor: [7, 7] });
  }
  function incidentIcon(kind) {
    return L.divIcon({ className: "", html: '<span class="map-dot ' + kind + '-dot"></span>', iconSize: [16, 16], iconAnchor: [8, 8] });
  }

  var style = document.createElement("style");
  style.textContent = ".map-dot{display:block;width:14px;height:14px;border-radius:99px;border:2px solid #fff;box-shadow:0 0 0 1px #0b1f3a}.stop-dot{background:#0b1f3a}.pending-dot{background:#6366f1}.verified-dot{background:#b45309}.resolved-dot{background:#65a30d}.other-dot{background:#0e7490}";
  document.head.appendChild(style);

  function addNetwork(data, opts) {
    opts = opts || {};
    var routeLayer = L.layerGroup();
    var stopLayer = L.layerGroup();
    var incidentLayer = L.layerGroup();
    var bounds = [];
    (data.routes || []).forEach(function (route) {
      if (!route.geometry || route.status === "inactive") return;
      var line = L.geoJSON(route.geometry, {
        style: { color: route.color || "#0f766e", weight: opts.weight || 4, opacity: 0.85 }
      });
      var impact = (route.impacts && route.impacts[0]) ? route.impacts[0].summary + " " + route.impacts[0].public_ref : "No active verified warning.";
      line.bindPopup("<strong>" + escapeHtml(route.code + " " + route.name) + "</strong><br>Demo data<br>" + escapeHtml(impact) + '<br><a href="/routes/' + route.id + '">Route details</a>');
      line.addTo(routeLayer);
      if (route.geometry.coordinates) {
        route.geometry.coordinates.forEach(function (pair) { bounds.push([pair[1], pair[0]]); });
      }
    });
    (data.stops || []).forEach(function (stop) {
      if (stop.active === false) return;
      var marker = L.marker([stop.latitude, stop.longitude], { icon: stopIcon(), keyboard: true, title: stop.name });
      marker.bindPopup("<strong>" + escapeHtml(stop.name) + "</strong><br>" + escapeHtml(stop.locality) + '<br><a href="/stops/' + stop.id + '">Stop details</a>');
      marker.addTo(stopLayer);
    });
    (data.incidents || []).forEach(function (incident) {
      var kind = incident.status === "verified" ? "verified" : (incident.status === "resolved" ? "resolved" : (incident.status === "pending_review" ? "pending" : "other"));
      var marker = L.marker([incident.latitude, incident.longitude], { icon: incidentIcon(kind), keyboard: true, title: incident.title });
      marker.bindPopup("<strong>" + escapeHtml(incident.public_ref) + "</strong><br>" + escapeHtml(incident.trust_label) + "<br>" + escapeHtml(incident.title) + '<br><a href="/incidents/' + incident.id + '">Incident details</a>');
      marker.addTo(incidentLayer);
      bounds.push([incident.latitude, incident.longitude]);
    });
    if (opts.routes !== false) routeLayer.addTo(map);
    if (opts.stops !== false) stopLayer.addTo(map);
    if (opts.incidents !== false) incidentLayer.addTo(map);
    if (bounds.length) map.fitBounds(bounds, { padding: [24, 24], maxZoom: 14 });
    var routesToggle = document.getElementById("layer-routes");
    var stopsToggle = document.getElementById("layer-stops");
    var incidentsToggle = document.getElementById("layer-incidents");
    function bind(box, layer) {
      if (!box) return;
      box.addEventListener("change", function () {
        if (box.checked) layer.addTo(map); else map.removeLayer(layer);
      });
    }
    bind(routesToggle, routeLayer);
    bind(stopsToggle, stopLayer);
    bind(incidentsToggle, incidentLayer);
  }

  function escapeHtml(value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
  }

  if (mode === "explore" || mode === "preview") {
    var embedded = readJson("map-data");
    if (embedded) addNetwork(embedded, { stops: mode !== "preview", incidents: true });
    else say("Map data did not load.");
  }

  if (mode === "journey") {
    var journey = readJson("journey-data");
    if (journey && journey.geometry) {
      var line = L.geoJSON(journey.geometry, { style: { color: "#0f766e", weight: 5 } }).addTo(map);
      map.fitBounds(line.getBounds(), { padding: [20, 20] });
      (journey.stops || []).forEach(function (stop) {
        L.marker([stop.lat, stop.lng], { icon: stopIcon(), title: stop.name }).addTo(map).bindPopup(escapeHtml(stop.name));
      });
    } else {
      say("Search two places to draw a supported journey. No route is invented when the dataset has no match.");
    }
  }

  if (mode === "line") {
    var lineData = readJson("line-data");
    if (lineData && lineData.geometry) {
      var drawn = L.geoJSON(lineData.geometry, { style: { color: "#0b1f3a", weight: 5 } }).addTo(map);
      (lineData.stops || []).forEach(function (stop) {
        L.circleMarker([stop.lat, stop.lng], { radius: 6, color: "#0f766e", fillOpacity: 0.9 }).addTo(map).bindPopup(escapeHtml(stop.name));
      });
      map.fitBounds(drawn.getBounds(), { padding: [24, 24] });
    }
  }

  if (mode === "point") {
    var point = readJson("point-data");
    if (point) {
      L.marker([point.lat, point.lng], { keyboard: true, title: point.label || "Location" }).addTo(map);
      map.setView([point.lat, point.lng], 15);
    }
  }

  if (mode === "pin") {
    var lat = document.getElementById("latitude");
    var lng = document.getElementById("longitude");
    var marker = null;
    function setPin(latlng) {
      if (!inBox(latlng.lat, latlng.lng)) {
        say("That point is outside the covered Accra area. Choose a point inside Greater Accra.");
        return;
      }
      if (marker) marker.setLatLng(latlng); else marker = L.marker(latlng, { draggable: true, keyboard: true }).addTo(map);
      marker.on("dragend", function () { setPin(marker.getLatLng()); });
      lat.value = latlng.lat.toFixed(6);
      lng.value = latlng.lng.toFixed(6);
      say("Pin set. You can also type coordinates if the map is hard to use.");
    }
    map.on("click", function (event) { setPin(event.latlng); });
    if (lat.value && lng.value) setPin(L.latLng(parseFloat(lat.value), parseFloat(lng.value)));
    var geoBtn = document.getElementById("use-location");
    if (geoBtn) {
      geoBtn.addEventListener("click", function () {
        if (!navigator.geolocation) {
          say("This browser has no location service. Search or tap the map instead.");
          return;
        }
        say("Asking the browser for your location. You can refuse and place the pin yourself.");
        navigator.geolocation.getCurrentPosition(function (pos) {
          var ll = L.latLng(pos.coords.latitude, pos.coords.longitude);
          if (!inBox(ll.lat, ll.lng)) {
            say("Your location is outside the covered area. Place the pin manually.");
            return;
          }
          map.setView(ll, 15);
          setPin(ll);
        }, function () {
          say("Location permission was denied or unavailable. Search for a place or tap the map.");
        }, { enableHighAccuracy: false, timeout: 8000 });
      });
    }
    fetch("/api/map").then(function (r) { return r.json(); }).then(function (body) {
      if (body.data) addNetwork(body.data, { incidents: true, weight: 3 });
    }).catch(function () { say("Route layer could not load. You can still place a pin or type coordinates."); });
  }

  function inBox(lat, lng) {
    return lat >= 5.5 && lat <= 5.75 && lng >= -0.36 && lng <= -0.12;
  }

  setTimeout(function () { map.invalidateSize(); }, 200);
})();
