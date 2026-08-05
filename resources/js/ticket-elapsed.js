// Alpine component for the live "Generated Time" column on the Support
// Tickets list (resources/views/support-tickets/index.blade.php). Ticks
// against the ticket's created_at timestamp (a server-computed ISO string)
// rather than a precomputed duration, so it stays correct regardless of how
// long the page has been open — same approach as rawDataCountdown. Only
// used for tickets that haven't been resolved yet; resolved tickets render
// a static "Solved in N days, N hour and N min" value server-side instead
// (mirroring SupportTicket::elapsedFormatted()).
window.ticketElapsed = function (createdAtIso) {
    return {
        text: '',

        init() {
            this.tick();
            setInterval(() => this.tick(), 30000);
        },

        tick() {
            const totalMinutes = Math.max(Math.floor((Date.now() - new Date(createdAtIso).getTime()) / 60000), 0);
            const days = Math.floor(totalMinutes / 1440);
            const hours = Math.floor((totalMinutes % 1440) / 60);
            const minutes = totalMinutes % 60;

            this.text = `${days} days, ${hours} hour and ${minutes} min`;
        },
    };
};
