
module PrettyPrinter {

    function expandable(closedContent:() => Node, openContent:() => Node):Node {
        var open = false;
        var toggle = document.createElement('a');
        toggle.href = 'javascript:void(0)';
        toggle.style.verticalAlign = 'text-bottom';
        var container = document.createElement('div');
        container.style.display = 'inline-block';

        function refresh() {
            toggle.textContent = open ? 'collapse' : 'expand';
            container.innerHTML = '';
            container.appendChild(open ? openContent() : closedContent());
        }

        toggle.addEventListener('click', function (e) {
            open = !open;
            refresh();
        });

        refresh();

        var wrapper = document.createElement('div');
        wrapper.style.display = 'inline-block';
        wrapper.appendChild(toggle);
        wrapper.appendChild(container);

        return wrapper;
    }

    function createTable(data:Node[][]):Node {
        var table = document.createElement('table');
        table.style.borderSpacing = '0';
        table.style.padding = '0';

        for (var x = 0; x < data.length; x++) {
            var row = document.createElement('tr');
            table.appendChild(row);
            for (var y = 0; y < data[x].length; y++) {
                var td = document.createElement('td');
                td.style.verticalAlign = 'top';
                td.style.padding = '0';
                td.appendChild(data[x][y]);
                row.appendChild(td);
            }
        }

        return table;
    }

    function bold(content:string):Node {
        var box = document.createElement('span');
        box.appendChild(document.createTextNode(content));
        box.style.fontWeight = 'bold';
        return box;
    }

    function keyword(word:string) {
        var box = document.createElement('span');
        box.appendChild(document.createTextNode(word));
        box.style.color = '#008';
        box.style.fontWeight = 'bold';
        return box;
    }

    function renderString(x:string):Node {
        var result = document.createElement('span');
        result.style.color = '#080';
        result.style.fontWeight = 'bold';

        var translate = {
            '\\': '\\\\',
            '$': '\\$',
            '\r': '\\r',
            '\v': '\\v',
            '\f': '\\f',
            '"': '\\"'
        };

        result.appendChild(document.createTextNode('"'));

        for (var i = 0; i < x.length; i++) {
            var char:string = x.charAt(i);
            var code:number = x.charCodeAt(i);

            if (translate[char] !== undefined) {
                result.appendChild(keyword(translate[char]));
            } else if ((code >= 32 && code <= 126) || char === '\n' || char === '\t') {
                result.appendChild(document.createTextNode(char));
            } else {
                result.appendChild(keyword('\\x' + (code < 10 ? '0' + code.toString(16) : code.toString(16))));
            }
        }

        result.appendChild(document.createTextNode('"'));
        return result;
    }

    function renderInt(x:number):Node {
        var result = document.createElement('span');
        result.appendChild(document.createTextNode(String(x)));
        result.style.color = '#00F';
        return result;
    }

    function renderFloat(x:number):Node {
        var result = document.createElement('span');
        result.appendChild(document.createTextNode(String(x)));
        return result;
    }

    function renderBool(x:boolean):Node {
        return keyword(x ? 'true' : 'false');
    }

    function renderNull():Node {
        return keyword('null');
    }

    function renderArray(x:any):Node {
        var result = document.createElement('span');
        result.appendChild(document.createTextNode('array ...'))
        return result;
    }

    function renderUnknown() {
        var result = document.createElement('span');
        result.appendChild(document.createTextNode('unknown'));
        return result;
    }

    function renderObject(x, root):Node {
        var object = root['objects'][x[1]];
        var result = document.createElement('span');
        result.appendChild(keyword('new'));
        result.appendChild(document.createTextNode(' '));
        result.appendChild(document.createTextNode(object['class']));
        result.appendChild(document.createTextNode(' '));
        result.appendChild(document.createTextNode('{'));
        result.appendChild(expandable(function () {
            return document.createTextNode('');
        }, function () {
            return document.createTextNode('')
        }));
        result.appendChild(document.createTextNode('}'));
        return result;
    }

    function renderStack(stack:any[], root):Node {
        var rows = [];

        for (var x = 0; x < stack.length; x++) {
            rows.push([
                renderLocation(stack[x]['location']),
                document.createTextNode(' '),
                renderFunctionCall(stack[x], root)
            ]);
        }

        return createTable(rows);
    }

    function renderFunctionCall(call:any, root):Node {
        var result = document.createElement('span');
        if (call['object']) {
            result.appendChild(renderObject(call['object'], root));
            result.appendChild(document.createTextNode('->'));
        } else if (call['class']) {
            result.appendChild(document.createTextNode(call['class'] + (call['isStatic'] ? '::' : '->')));
        }

        result.appendChild(document.createTextNode(call['function'] + '('));

        for (var i = 0; i < call['args'].length; i++) {
            if (i != 0)
                result.appendChild(document.createTextNode(', '));

            result.appendChild(renderAny(call['args'][i], root));
        }

        result.appendChild(document.createTextNode(')'));

        return result;
    }

    function renderVariable(name:string):Node {
        var result = document.createElement('span');
        result.appendChild(document.createTextNode('$' + name));
        result.style.color = '#800';
        return result;
    }

    function renderLocals(locals, root):Node {
        var result = document.createElement('div');
        result.style.display = 'inline-block';

        if (locals instanceof Array) {
            if (!locals) {
                result.appendChild(document.createTextNode('none'));
            } else {
                var rows = [];

                for (var i = 0; i < locals.length; i++) {
                    var local = locals[i];
                    var name = local['name'];
                    var value = document.createElement('div');
                    value.appendChild(renderAny(local['value'], root));
                    value.appendChild(document.createTextNode(';'));
                    rows.push([renderVariable(name), document.createTextNode(' = '), value]);
                }

                result.appendChild(createTable(rows));
            }
        } else {
            result.appendChild(document.createTextNode('n/a'));
        }

        return result;
    }

    function renderException(x, root):Node {
        if (!x)
            return document.createTextNode('none');

        return createTable([
            [bold('class '), document.createTextNode(x['class'])],
            [bold('code '), document.createTextNode(x['code'])],
            [bold('message '), document.createTextNode(x['message'])],
            [bold('location '), renderLocation(x['location'])],
            [bold('stack '), renderStack(x['stack'], root)],
            [bold('locals '), renderLocals(x['locals'], root)],
            [bold('globals '), document.createTextNode('global variables...')],
            [bold('previous '), renderException(x['preivous'], root)]
        ]);
    }

    function renderLocation(location):Node {
        var wrapper = document.createElement('span');
        wrapper.appendChild(document.createTextNode(location['file']));
        wrapper.appendChild(document.createTextNode(':'));
        wrapper.appendChild(renderInt(location['line']));

        function closedContent():Node {
            return wrapper;
        }

        function openContent():Node {
            var s = document.createElement('span');
            s.appendChild(document.createTextNode('open!'));
            return s;
        }

        return expandable(closedContent, openContent);
    }

    function renderAny(root, v):Node {
        if (typeof root === 'string')
            return renderString(root);
        else if (typeof root === 'number')
            if (root % 1 === 0)
                return renderInt(root);
            else
                return renderFloat(root);
        else if (typeof root === 'boolean')
            return renderBool(root);
        else if (root === null)
            return renderNull();
        else if (root[0] === 'float')
            if (root[1] === 'inf' || root[1] === '+inf')
                return renderFloat(Infinity);
            else if (root[1] === '-inf')
                return renderFloat(-Infinity);
            else if (root[1] === 'nan')
                return renderFloat(NaN);
            else
                return renderFloat(root[1]);
        else if (root[0] === 'array')
            return renderArray(root[1]);
        else if (root[0] === 'unknown')
            return renderUnknown();
        else if (root[0] === 'object')
            return renderObject(root, v);
        else if (root[0] === 'exception')
            return renderException(root[1], v);
        else
            throw { message: "not goord" };
    }

    function renderWhole(v):Node {
        return renderAny(v['root'], v);
    }

    function start() {
        var body = document.getElementsByTagName('body')[0];
        var text = document.createElement('textarea');
        body.appendChild(text);
        text.style.width = '800px';
        text.style.height = '500px';
        var container = document.createElement('div');
        body.appendChild(container);

        function onchange() {
            var json:string = text.value;
            var parsedJSON = JSON.parse(json);
            var rendered:Node = renderWhole(parsedJSON);
            container.innerHTML = '';
            container.appendChild(rendered);
        }

        text.addEventListener('change', onchange);

        text.value = "{\"root\":[\"exception\",{\"class\":\"MuhMockException\",\"code\":\"Dummy exception code\",\"message\":\"This is a dummy exception message.\\n\\nlololool\",\"location\":{\"line\":9000,\"file\":\"\\/path\\/to\\/muh\\/file\",\"sourceCode\":null},\"previous\":null,\"stack\":[{\"function\":\"aFunction\",\"class\":\"DummyClass1\",\"isStatic\":null,\"location\":{\"line\":1928,\"file\":\"\\/path\\/to\\/muh\\/file\",\"sourceCode\":null},\"object\":[\"object\",0],\"args\":[[\"object\",1]]},{\"function\":\"aFunction\",\"class\":null,\"isStatic\":null,\"location\":{\"line\":1928,\"file\":\"\\/path\\/to\\/muh\\/file\",\"sourceCode\":null},\"object\":null,\"args\":[[\"object\",2]]}],\"locals\":[{\"name\":\"lol\",\"value\":8},{\"name\":\"foo\",\"value\":\"bar\"}],\"globals\":{\"staticProperties\":[{\"name\":\"blahProperty\",\"value\":null,\"class\":\"BlahClass\",\"access\":\"private\",\"isDefault\":false}],\"globalVariables\":[{\"name\":\"lol global\",\"value\":null},{\"name\":\"blahVariable\",\"value\":null}],\"staticVariables\":[{\"name\":\"public\",\"value\":null,\"class\":null,\"function\":\"BlahAnotherClass\"},{\"name\":\"lolStatic\",\"value\":null,\"class\":\"BlahYetAnotherClass\",\"function\":\"blahMethod\"}]}}],\"arrays\":[],\"objects\":[{\"class\":\"ErrorHandler\\\\DummyClass1\",\"hash\":\"0000000058b5388000000000367cf886\",\"properties\":[{\"name\":\"private1\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass1\",\"access\":\"private\",\"isDefault\":true},{\"name\":\"protected1\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass1\",\"access\":\"protected\",\"isDefault\":true},{\"name\":\"public1\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass1\",\"access\":\"public\",\"isDefault\":true}]},{\"class\":\"ErrorHandler\\\\DummyClass2\",\"hash\":\"0000000058b5388300000000367cf886\",\"properties\":[{\"name\":\"private2\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass2\",\"access\":\"private\",\"isDefault\":true},{\"name\":\"protected2\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass2\",\"access\":\"protected\",\"isDefault\":true},{\"name\":\"public2\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass2\",\"access\":\"public\",\"isDefault\":true},{\"name\":\"private1\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass1\",\"access\":\"private\",\"isDefault\":true},{\"name\":\"protected1\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass1\",\"access\":\"protected\",\"isDefault\":true},{\"name\":\"public1\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass1\",\"access\":\"public\",\"isDefault\":true}]},{\"class\":\"ErrorHandler\\\\DummyClass2\",\"hash\":\"0000000058b5388a00000000367cf886\",\"properties\":[{\"name\":\"private2\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass2\",\"access\":\"private\",\"isDefault\":true},{\"name\":\"protected2\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass2\",\"access\":\"protected\",\"isDefault\":true},{\"name\":\"public2\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass2\",\"access\":\"public\",\"isDefault\":true},{\"name\":\"private1\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass1\",\"access\":\"private\",\"isDefault\":true},{\"name\":\"protected1\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass1\",\"access\":\"protected\",\"isDefault\":true},{\"name\":\"public1\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass1\",\"access\":\"public\",\"isDefault\":true}]}]}";
        onchange();
    }

    document.addEventListener('DOMContentLoaded', start);
}