function displayDateTime() {
    const currentDate = new Date();
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', /*hour: '2-digit', minute: '2-digit', second: '2-digit'*/ };
    document.getElementById('currentTime').innerText = currentDate.toLocaleString('fr-FR', options);
}

// Mise à jour de l'heure toutes les secondes
setInterval(displayDateTime, 1000);
