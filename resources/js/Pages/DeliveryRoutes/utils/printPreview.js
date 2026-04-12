/**
 * Generate a printable route preview in a new window.
 *
 * @param {string} mode - 'map', 'list', or 'both'
 * @param {object} params
 * @param {object} params.optimizedData - Result from optimize-preview endpoint
 * @param {string} params.driverName - Driver's name
 * @param {string} params.dateRoute - Route date (YYYY-MM-DD)
 * @param {object|null} params.startPoint - { name, lat, lng }
 */
export function printRoute(mode, { optimizedData, driverName, dateRoute, startPoint }) {
    if (!optimizedData?.deliveries) return;
    const deliveries = optimizedData.deliveries;
    const geoDeliveries = deliveries.filter(d => d.lat && d.lng);

    let mapHtml = '';
    if (mode !== 'list' && geoDeliveries.length > 0) {
        const allLats = geoDeliveries.map(d => d.lat);
        const allLngs = geoDeliveries.map(d => d.lng);
        if (optimizedData.start_point) { allLats.push(optimizedData.start_point.lat); allLngs.push(optimizedData.start_point.lng); }
        const centerLat = (Math.min(...allLats) + Math.max(...allLats)) / 2;
        const centerLng = (Math.min(...allLngs) + Math.max(...allLngs)) / 2;

        mapHtml = `
            <div style="page-break-inside: avoid; margin-bottom: 20px;">
                <h2 style="font-size: 16px; font-weight: bold; margin-bottom: 10px; border-bottom: 2px solid #4f46e5; padding-bottom: 5px;">Mapa da Rota</h2>
                <div style="border: 1px solid #ddd; border-radius: 8px; overflow: hidden; height: 400px; position: relative;" id="print-map"></div>
            </div>
            <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
            <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"><\/script>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var map = L.map('print-map').setView([${centerLat}, ${centerLng}], 12);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '© OpenStreetMap'
                    }).addTo(map);

                    ${optimizedData.start_point ? `
                    L.marker([${optimizedData.start_point.lat}, ${optimizedData.start_point.lng}], {
                        icon: L.divIcon({
                            className: '',
                            html: '<div style="background:#dc2626;color:white;border-radius:50%;width:28px;height:28px;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:bold;box-shadow:0 2px 4px rgba(0,0,0,0.3);border:2px solid white">CD</div>',
                            iconSize: [28, 28], iconAnchor: [14, 14]
                        })
                    }).addTo(map).bindPopup('<b>CD - Ponto de Saída</b>');
                    ` : ''}

                    var points = [];
                    ${geoDeliveries.map((d) => `
                    (function(){
                        var p = [${d.lat}, ${d.lng}];
                        points.push(p);
                        L.marker(p, {
                            icon: L.divIcon({
                                className: '',
                                html: '<div style="background:#4f46e5;color:white;border-radius:50%;width:24px;height:24px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:bold;box-shadow:0 2px 4px rgba(0,0,0,0.3)">${d.sequence}</div>',
                                iconSize: [24, 24], iconAnchor: [12, 12]
                            })
                        }).addTo(map).bindPopup('<b>${d.sequence}.</b> ${d.client_name.replace(/'/g, "\\'")}');
                    })();
                    `).join('')}

                    ${optimizedData.geometry?.geometry?.coordinates ? `
                    var routeCoords = ${JSON.stringify(optimizedData.geometry.geometry.coordinates.map(c => [c[1], c[0]]))};
                    L.polyline(routeCoords, {color: '#4f46e5', weight: 3, opacity: 0.7}).addTo(map);
                    ` : `
                    if (points.length > 1) {
                        ${optimizedData.start_point ? `points.unshift([${optimizedData.start_point.lat}, ${optimizedData.start_point.lng}]);` : ''}
                        L.polyline(points, {color: '#4f46e5', weight: 3, opacity: 0.7, dashArray: '8,6'}).addTo(map);
                    }
                    `}

                    var allPoints = points.slice();
                    ${optimizedData.start_point ? `allPoints.push([${optimizedData.start_point.lat}, ${optimizedData.start_point.lng}]);` : ''}
                    if (allPoints.length > 0) map.fitBounds(allPoints, {padding: [30, 30]});

                    map.whenReady(function() {
                        setTimeout(function() {
                            document.getElementById('print-btn').disabled = false;
                            document.getElementById('print-btn').textContent = 'Imprimir';
                        }, 1500);
                    });
                });
            <\/script>
        `;
    }

    let listHtml = '';
    if (mode !== 'map') {
        const rows = deliveries.map(d => `
            <tr>
                <td style="padding: 8px 12px; border-bottom: 1px solid #e5e7eb; text-align: center; font-weight: bold; color: #4f46e5;">${d.sequence}</td>
                <td style="padding: 8px 12px; border-bottom: 1px solid #e5e7eb; font-weight: 500;">${d.client_name}</td>
                <td style="padding: 8px 12px; border-bottom: 1px solid #e5e7eb; font-size: 13px; color: #555;">${d.address || '-'}</td>
                <td style="padding: 8px 12px; border-bottom: 1px solid #e5e7eb; text-align: center; font-size: 12px;">${d.lat ? '\u2713' : '\u2014'}</td>
            </tr>
        `).join('');

        listHtml = `
            <div style="page-break-inside: avoid;">
                <h2 style="font-size: 16px; font-weight: bold; margin-bottom: 10px; border-bottom: 2px solid #4f46e5; padding-bottom: 5px;">
                    Sequ\u00eancia de Entregas (${deliveries.length})
                </h2>
                <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                    <thead>
                        <tr style="background: #f3f4f6;">
                            <th style="padding: 8px 12px; text-align: center; font-size: 12px; font-weight: 600; color: #374151; border-bottom: 2px solid #d1d5db; width: 50px;">#</th>
                            <th style="padding: 8px 12px; text-align: left; font-size: 12px; font-weight: 600; color: #374151; border-bottom: 2px solid #d1d5db;">Cliente</th>
                            <th style="padding: 8px 12px; text-align: left; font-size: 12px; font-weight: 600; color: #374151; border-bottom: 2px solid #d1d5db;">Endere\u00e7o</th>
                            <th style="padding: 8px 12px; text-align: center; font-size: 12px; font-weight: 600; color: #374151; border-bottom: 2px solid #d1d5db; width: 50px;">GPS</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
        `;
    }

    const distInfo = optimizedData.distance_km ? `${optimizedData.distance_km} km · ~${optimizedData.duration_min} min` : '';

    const html = `<!DOCTYPE html>
<html><head>
    <meta charset="utf-8">
    <title>Roteiro de Entregas</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; margin: 0; padding: 20px; color: #1f2937; }
        @media print {
            body { padding: 10px; }
            #print-btn, #print-actions { display: none !important; }
            #print-map { height: 380px !important; }
        }
    </style>
</head><body>
    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 20px; border-bottom: 3px solid #4f46e5; padding-bottom: 15px;">
        <div>
            <h1 style="margin: 0; font-size: 22px; color: #1e1b4b;">Roteiro de Entregas</h1>
            <p style="margin: 4px 0 0; color: #6b7280; font-size: 14px;">Grupo Meia Sola \u2014 Mercury</p>
        </div>
        <div style="text-align: right; font-size: 13px; color: #374151;">
            <div><strong>Motorista:</strong> ${driverName}</div>
            <div><strong>Data:</strong> ${dateRoute.split('-').reverse().join('/')}</div>
            <div><strong>Entregas:</strong> ${deliveries.length} ${distInfo ? `\u00b7 ${distInfo}` : ''}</div>
            ${startPoint ? `<div><strong>Sa\u00edda:</strong> ${startPoint.name}</div>` : ''}
        </div>
    </div>
    <div id="print-actions" style="margin-bottom: 15px; text-align: right;">
        <button id="print-btn" onclick="window.print()" ${mode !== 'list' ? 'disabled' : ''}
            style="padding: 8px 20px; background: #4f46e5; color: white; border: none; border-radius: 6px; font-size: 14px; cursor: pointer;">
            ${mode !== 'list' ? 'Carregando mapa...' : 'Imprimir'}
        </button>
    </div>
    ${mapHtml}
    ${listHtml}
</body></html>`;

    const win = window.open('', '_blank');
    win.document.write(html);
    win.document.close();
    if (mode === 'list') {
        setTimeout(() => win.print(), 300);
    }
}
