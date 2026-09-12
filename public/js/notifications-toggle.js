document.addEventListener('DOMContentLoaded', function () {
    var button = document.querySelector('[data-notifications-toggle]');
    var list = document.querySelector('[data-notification-list]');
    var footer = document.querySelector('[data-notifications-footer]');
    if (!button || !list) return;

    var expanded = false;
    var loaded = false;
    var previewCount = parseInt(button.getAttribute('data-preview-count'), 10) || 5;
    var total = parseInt(button.getAttribute('data-total'), 10) || previewCount;
    var moreUrl = button.getAttribute('data-more-url');

    function extraItems() {
        return list.querySelectorAll('[data-notification-extra]');
    }

    function updateFooter(shown) {
        if (footer) {
            footer.textContent = 'Showing ' + shown + ' of ' + total + ' notification(s)';
        }
    }

    function setExpanded(isExpanded) {
        expanded = isExpanded;
        extraItems().forEach(function (item) {
            item.hidden = !isExpanded;
        });
        button.textContent = isExpanded ? 'Show Less Notifications' : 'See More Notifications';
        updateFooter(isExpanded ? list.querySelectorAll('.notification-item').length : previewCount);
    }

    button.addEventListener('click', function () {
        if (expanded) {
            setExpanded(false);
            return;
        }

        if (loaded) {
            setExpanded(true);
            return;
        }

        button.disabled = true;

        fetch(moreUrl, {
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
                if (data.html) {
                    list.insertAdjacentHTML('beforeend', data.html);
                }
                if (data.total) {
                    total = data.total;
                }
                loaded = true;
                setExpanded(true);
            })
            .catch(function () {
                button.textContent = 'See More Notifications';
            })
            .finally(function () {
                button.disabled = false;
            });
    });
});
