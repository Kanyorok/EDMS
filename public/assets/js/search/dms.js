/**
 * DMS global search.
 *
 * Three datasets share one input: digital files, repositories, and - for users
 * allowed to see them - the paper files held in the registries of their branch.
 * The physical-files dataset is only wired when the input says it may be; the
 * endpoint enforces the same permission regardless.
 *
 * Replaces the former dms.min.js, which carried the first two datasets only.
 */
const $searchInput = $('#SearchInput');

$(function () {
    const searchUrl = $searchInput.data('url');
    const withPhysicalFiles = Boolean($searchInput.data('physical-files'));

    function engine(type, tokenizeOn) {
        const source = new Bloodhound({
            datumTokenizer: function (item) {
                return Bloodhound.tokenizers.whitespace(item[tokenizeOn]);
            },
            queryTokenizer: Bloodhound.tokenizers.whitespace,
            remote: {
                url: searchUrl + '?type=' + type + '&q=%QUERY',
                wildcard: '%QUERY',
                filter: function (response) {
                    return response.data;
                }
            }
        });

        source.initialize();

        return source;
    }

    function messages(heading) {
        return {
            header: '<h6 class="suggestions-header text-primary mb-0 mx-3 mt-3 pb-2">' + heading + '</h6>',
            notFound: '<div class="not-found px-3 py-2"><h6 class="suggestions-header text-primary mb-2">'
                + heading + '</h6><p class="py-2 mb-0"><i class="bx bx-error-circle bx-xs me-2"></i>'
                + ' No Results Found</p></div>',
            pending: '<div class="not-found px-3 py-2"><h6 class="suggestions-header text-primary mb-2">'
                + heading + '</h6><p class="py-2 mb-0">please wait</p></div>'
        };
    }

    function escapeHtml(value) {
        return $('<div>').text(value === null || value === undefined ? '' : value).html();
    }

    const datasets = [
        {
            name: 'files',
            displayKey: 'value',
            source: engine('files', 'name').ttAdapter(),
            limit: 5,
            templates: Object.assign(messages('Files'), {
                suggestion: function (document) {
                    return '<a href="' + document.links.detail + '">'
                        + '<div class="d-flex align-items-center">'
                        + '<img class="rounded-circle me-3" src="' + document.type.img + '" alt="' + document.id + '" height="32">'
                        + '<div class="user-info"><h6 class="mb-0">' + escapeHtml(document.name) + '</h6>'
                        + '<small class="text-muted">' + escapeHtml(document.size.string) + '</small>'
                        + '</div></div></a>';
                }
            })
        },
        {
            name: 'repositories',
            displayKey: 'value',
            source: engine('repositories', 'name').ttAdapter(),
            limit: 5,
            templates: Object.assign(messages('Repositories'), {
                suggestion: function (repo) {
                    return '<a href="' + repo.links.route + '"><div class="d-flex">'
                        + '<div class="flex-shrink-0"><svg class="pc-icon wid-20 hei-20 '
                        + (repo.visibility.value === 'pri' ? 'text-warning' : 'text-primary') + '">'
                        + '<use xlink:href="#custom-folder-open"></use></svg></div>'
                        + '<div class="flex-grow-1 mx-3"><h5 class="mb-1 d-grid">'
                        + '<span class="text-truncate w-100">' + escapeHtml(repo.name) + '</span></h5>'
                        + '</div></div></a>';
                }
            })
        }
    ];

    if (withPhysicalFiles) {
        datasets.push({
            name: 'physical-files',
            displayKey: 'value',
            source: engine('physical-files', 'name').ttAdapter(),
            limit: 5,
            templates: Object.assign(messages('Physical Files'), {
                suggestion: function (file) {
                    return '<a href="' + file.links.detail + '"><div class="d-flex align-items-center">'
                        + '<div class="flex-shrink-0"><i class="fas fa-folder-open text-primary fa-lg"></i></div>'
                        + '<div class="flex-grow-1 mx-3"><h6 class="mb-0">'
                        + escapeHtml(file.name) + ' <span class="text-muted">' + escapeHtml(file.title) + '</span></h6>'
                        + '<small class="text-muted">' + escapeHtml(file.location)
                        + ' &bull; ' + file.documents + ' document(s) &bull; ' + escapeHtml(file.status)
                        + '</small></div></div></a>';
                }
            })
        });
    }

    $searchInput.typeahead.apply($searchInput, [{highlight: false}].concat(datasets));
});
