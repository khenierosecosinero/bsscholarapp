document.addEventListener('DOMContentLoaded', function () {
    var button = document.getElementById('notifications-toggle');
    var list = document.querySelector('[data-notification-list]');
    var footer = document.getElementById('notifications-footer');
    if (!button || !list) return;

    var expanded = false;
    var loading = false;
    var total = parseInt(button.getAttribute('data-total'), 10) || 0;
    var moreUrl = button.getAttribute('data-more-url');

    function items() {
        return list.querySelectorAll('.notification-item');
    }

    function extraItems() {
        return list.querySelectorAll('[data-notification-extra]');
    }

    function visibleCount() {
        var count = 0;
        items().forEach(function (item) {
            if (!item.hidden) count += 1;
        });
        return count;
    }

    function updateFooter() {
        if (!footer) return;
        footer.textContent = 'Showing ' + visibleCount() + ' of ' + total + ' notification(s)';
    }

    function setExpanded(isExpanded) {
        expanded = isExpanded;
        extraItems().forEach(function (item) {
            item.hidden = !isExpanded;
        });
        button.textContent = isExpanded ? 'See Less Notifications' : 'See More Notifications';
        button.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
        updateFooter();
    }

    function existingIds() {
        return Array.prototype.map.call(items(), function (item) {
            return item.getAttribute('data-id');
        }).filter(Boolean);
    }

    button.addEventListener('click', function (event) {
        event.preventDefault();
        if (loading) return;

        if (expanded) {
            setExpanded(false);
            return;
        }

        if (extraItems().length) {
            setExpanded(true);
            return;
        }

        if (!moreUrl) return;

        var url;
        try {
            url = new URL(moreUrl, window.location.origin);
        } catch (error) {
            return;
        }

        var ids = existingIds();
        if (ids.length) {
            url.searchParams.set('except', ids.join(','));
        }

        loading = true;
        button.disabled = true;

        fetch(url.toString(), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
            .then(function (response) {
                if (!response.ok) throw new Error('Unable to load more notifications.');
                return response.json();
            })
            .then(function (data) {
                if (typeof data.total === 'number') {
                    total = data.total;
                }

                if (data.html) {
                    var holder = document.createElement('div');
                    holder.innerHTML = data.html;
                    var known = {};
                    existingIds().forEach(function (id) {
                        known[id] = true;
                    });

                    Array.prototype.forEach.call(holder.querySelectorAll('.notification-item'), function (item) {
                        var id = item.getAttribute('data-id');
                        if (id && known[id]) return;
                        item.setAttribute('data-notification-extra', '');
                        item.hidden = true;
                        list.appendChild(item);
                        if (id) known[id] = true;
                    });
                }

                if (!extraItems().length) {
                    button.hidden = true;
                    updateFooter();
                    return;
                }

                setExpanded(true);
            })
            .catch(function () {
                button.textContent = 'See More Notifications';
                button.setAttribute('aria-expanded', 'false');
            })
            .finally(function () {
                loading = false;
                button.disabled = false;
            });
    });
});
