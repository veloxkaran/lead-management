// Alpine component for the live "Generated Time" column on the Support
// Tickets list (resources/views/support-tickets/index.blade.php). Ticks
// against the ticket's created_at timestamp (a server-computed ISO string)
// rather than a precomputed duration, so it stays correct regardless of how
// long the page has been open — same approach as rawDataCountdown. Only
// used for tickets that haven't been resolved yet; resolved tickets render
// a static "Solved in N min" value server-side instead.
window.ticketElapsed = function (createdAtIso) {
    return {
        text: '',

        init() {
            this.tick();
            setInterval(() => this.tick(), 30000);
        },

        tick() {
            const minutes = Math.floor((Date.now() - new Date(createdAtIso).getTime()) / 60000);
            this.text = `${Math.max(minutes, 0)} min`;
        },
    };
};
