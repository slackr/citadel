describe("Parallax", function() {
    var host = 'http://localhost'; // run test from http://localhost/test/test.html only
    var url = host + '/';

    describe("verify-session", function() {
        url = host + '/verify-session/';

        var test_vs = function(test_name, data, expected_status) {
            it("'" + test_name + "' should return " + expected_status, function(done) {
                var resolved = function(r) {
                    console.log(r);
                    expect(r.session_id).toEqual(data.session_id);
                    expect(r.session_ip).toEqual(data.session_ip);
                    expect(r.status).toEqual(expected_status);
                };
                var rejected = function(e) {
                    console.log(e);
                    expect('ajax post error: ' + e.statusText + ':' + e.status).toBeUndefined();
                    done();
                };

                $.ajax({
                    type: "POST",
                    url: url,
                    data: data,
                    dataType: 'json',
                })
                .done(function (data) { resolved(data); })
                .fail(function (err) { rejected(err); })
                .always(function() { done() });

            });
        }

        var mock = {
            'bad_sid': {
                data: { session_id: 'bad_sid', session_ip: '127.0.0.1' },
                expected_status: 'error',
            },
            'bad_ip': {
                data: { session_id: 'c5379fhahq7mt3dse613omc1j4', session_ip: '127.0.0.2' },
                expected_status: 'error',
            }
        }
        for (var t in mock) {
            test_vs(t, mock[t].data, mock[t].expected_status);
        }

    });
});
