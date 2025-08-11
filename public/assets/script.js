// Recherche live client
document.addEventListener('DOMContentLoaded', function() {
    const search = document.getElementById('client-search');
    if (search) {
        search.addEventListener('input', function() {
            fetch('?route=api_search_clients&q=' + encodeURIComponent(search.value))
            .then(r => r.json())
            .then(data => {
                const list = document.getElementById('client-list');
                list.innerHTML = '';
                data.forEach(c => {
                    const li = document.createElement('li');
                    const a = document.createElement('a');
                    a.href = '?route=client_details&id=' + c.id;
                    a.textContent = c.name;
                    li.appendChild(a);
                    list.appendChild(li);
                });
            });
        });
    }
});

// Tri des tableaux
function sortTable(tableId, colIndex) {
    var table = document.getElementById(tableId);
    var rows = Array.from(table.rows).slice(1);
    var dir = table.getAttribute("data-sortdir-" + colIndex) || "asc";
    rows.sort(function(a, b) {
        var aVal = a.cells[colIndex].innerText.trim();
        var bVal = b.cells[colIndex].innerText.trim();
        var aNum = parseFloat(aVal.replace(/[^\d.-]/g, ""));
        var bNum = parseFloat(bVal.replace(/[^\d.-]/g, ""));
        if (!isNaN(aNum) && !isNaN(bNum)) {
            return dir === "asc" ? aNum - bNum : bNum - aNum;
        }
        return dir === "asc" ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
    });
    table.setAttribute("data-sortdir-" + colIndex, dir === "asc" ? "desc" : "asc");
    var tbody = table.tBodies[0];
    rows.forEach(function(row) { tbody.appendChild(row); });
    var ths = table.rows[0].cells;
    for(let i=0; i<ths.length; i++) ths[i].classList.remove("sorted-asc", "sorted-desc");
    ths[colIndex].classList.add(dir === "asc" ? "sorted-asc" : "sorted-desc");
}