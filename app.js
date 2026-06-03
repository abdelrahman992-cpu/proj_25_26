// app.js
const api = {
    searchTerms: async (query) => {
        const res = await fetch(`api.php?action=search_terms&search=${encodeURIComponent(query)}`);
        return await res.json();
    },
    // ... باقي الدوال التي أرسلتها لك في الرد السابق ...
};
