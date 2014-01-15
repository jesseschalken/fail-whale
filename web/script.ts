module PrettyPrinter {

    function wrapNode(node:Node, inline:boolean = true):HTMLElement {
        var wrapper = document.createElement('span');
        wrapper.style.margin = '0.25em';
        wrapper.style.display = inline ? 'inline-block' : 'block';
        wrapper.style.verticalAlign = 'middle';
        wrapper.appendChild(node);
        return wrapper;
    }

    function block(node:Node):Node {
        var div = document.createElement('div');
        div.appendChild(node);
        return div;
    }

    function wrap(text:string):HTMLElement {
        var node = document.createTextNode(text);
        return wrapNode(node);
    }

    function italics(text:string) {
        var wrapped = wrap(text);
        wrapped.style.fontStyle = 'italic';
        return wrapped;
    }

    function collect(nodes:Node[]):Node {
        var x = document.createDocumentFragment();
        for (var i = 0; i < nodes.length; i++) {
            x.appendChild(nodes[i]);
        }
        return x;
    }

    function expandable2(headContent:Node, content:() => Node, inline:boolean = true):Node {
        var container = document.createElement('div');
        var head = document.createElement('div');
        head.style.backgroundColor = '#eee';
        head.style.cursor = 'pointer';
        head.addEventListener('mouseenter', function () {
            head.style.backgroundColor = '#ddd';
        });
        head.addEventListener('mouseleave', function () {
            head.style.backgroundColor = '#eee';
        });
        head.addEventListener('mousedown', function (e) {
            e.preventDefault();
        });
        head.appendChild(headContent);
        container.appendChild(head);
        container.style.backgroundColor = '#fff';
        var body = document.createElement('div');
        body.style.borderTopWidth = '0.125em';
        body.style.borderTopStyle = 'dashed';
        body.style.borderTopColor = '#888';
        var open = false;

        head.addEventListener('click', function () {
            if (open) {
                body.innerHTML = '';
                container.removeChild(body);
            } else {
                body.appendChild(content());
                container.appendChild(body);
            }

            open = !open;
        });

        return wrapNode(container, inline);
    }

    function createTable(data:Node[][]):HTMLTableElement {
        var table = document.createElement('table');
        table.style.borderSpacing = '0';
        table.style.padding = '0';

        for (var x = 0; x < data.length; x++) {
            var row = document.createElement('tr');
            table.appendChild(row);
            for (var y = 0; y < data[x].length; y++) {
                var td = document.createElement('td');
                td.style.padding = '0';
                td.appendChild(data[x][y]);
                row.appendChild(td);
            }
        }

        return table;
    }

    function bold(content:string):Node {
        var box = wrap(content);
        box.style.fontWeight = 'bold';
        return box;
    }

    function keyword(word:string) {
        var box = wrap(word);
        box.style.color = '#008';
        box.style.fontWeight = 'bold';
        return box;
    }

    function renderString2(x:string):{
        length:number;
        result:Node;
    } {
        var result = document.createElement('span');
        result.style.color = '#080';
        result.style.backgroundColor = '#dFd';
        result.style.fontWeight = 'bold';
        result.style.display = 'inline';

        var translate = {
            '\\': '\\\\',
            '$': '\\$',
            '\r': '\\r',
            '\v': '\\v',
            '\f': '\\f',
            '"': '\\"'
        };

        var length = 0;

        var buffer:string = '"';

        function flush() {
            if (buffer.length > 0)
                result.appendChild(document.createTextNode(buffer));

            buffer = "";
        }

        for (var i = 0; i < x.length; i++) {
            var char:string = x.charAt(i);
            var code:number = x.charCodeAt(i);

            function escaped(x:string):Node {
                var box = document.createElement('span');
                box.appendChild(document.createTextNode(x));
                box.style.color = '#008';
                box.style.fontWeight = 'bold';
                return box;
            }

            if (translate[char] !== undefined) {
                flush();
                result.appendChild(escaped(translate[char]));
                length += 2;
            } else if ((code >= 32 && code <= 126) || char === '\n' || char === '\t') {
                buffer += char;
                length += 1;
            } else {
                flush();
                result.appendChild(escaped('\\x' + (code < 10 ? '0' + code.toString(16) : code.toString(16))));
                length += 4;
            }
        }

        buffer += '"';

        flush();

        return { result: wrapNode(result), length: length };
    }

    function renderString(x:string):Node {
        var threshold = 200;

        if (x.length > threshold)
            return expandable2(keyword('string'), function () {
                return renderString2(x).result;
            });

        var result = renderString2(x);

        if (result.length > threshold)
            return expandable2(keyword('string'), function () {
                return result.result;
            });

        return result.result;
    }

    function renderInt(x:number):Node {
        var result = wrap(String(x));
        result.style.color = '#00F';
        return result;
    }

    function renderFloat(x:number):Node {
        var str = x % 1 == 0 ? String(x) + '.0' : String(x);
        var result = wrap(str);
        result.style.color = '#00F';
        return result;
    }

    function renderBool(x:boolean):Node {
        return keyword(x ? 'true' : 'false');
    }

    function renderNull():Node {
        return keyword('null');
    }

    function renderArray(x:any, root):Node {
        var array = root['arrays'][x[1]];
        var entries = array['entries'];
        return expandable2(keyword('array'), function () {
            if (entries.length == 0)
                return italics('empty');

            var rows:Node[][] = [];
            for (var i = 0; i < entries.length; i++) {
                var entry = entries[i];
                rows.push([
                    renderAny(entry[0], root),
                    wrap('=>'),
                    renderAny(entry[1], root)
                ]);
            }
            return  createTable(rows);
        });
    }

    function renderUnknown() {
        return bold('unknown type');
    }

    function renderObject(x, root):Node {
        var object = root['objects'][x[1]];
        var result = document.createDocumentFragment();
        result.appendChild(keyword('new'));
        result.appendChild(wrap(object['class']));

        function body() {
            var properties:Array<any> = object['properties'];
            var rows:Node[][] = [];
            for (var i = 0; i < properties.length; i++) {
                var property = properties[i];
                var variable = renderVariable(property['name']);
                var value = renderAny(property['value'], root);
                rows.push([
                    collect([keyword(property['access']), variable]),
                    wrap('='),
                    value
                ]);
            }
            return createTable(rows);
        }

        return expandable2(result, body);
    }

    function renderStack(stack:any[], root):Node {
        return expandable2(bold('stack trace'), function () {
            var rows:Node[][] = [];

            for (var x = 0; x < stack.length; x++) {
                var container = document.createDocumentFragment();
                var div1 = document.createElement('div');
                div1.appendChild(wrap('#' + String(x + 1)));
                div1.appendChild(renderLocation(stack[x]['location']));
                container.appendChild(div1);

                var div2 = document.createElement('div');
                div2.style.marginLeft = '4em';
                div2.style.marginBottom = '1em';
                div2.appendChild(renderFunctionCall(stack[x], root));
                container.appendChild(div2);

                rows.push([container]);
            }

            rows.push([collect([
                wrap('#' + String(x + 1)),
                expandable2(wrap('{main}'), function () {
                    return italics('n/a');
                })
            ])]);

            return createTable(rows);
        }, false);
    }

    function renderFunctionCall(call:any, root):Node {
        var result = document.createDocumentFragment();
        var prefix = '';
        if (call['object']) {
            result.appendChild(renderObject(call['object'], root));
            prefix += '->';
        } else if (call['class']) {
            prefix += call['class'];
            prefix += call['isStatic'] ? '::' : '->';
        }

        result.appendChild(wrap(prefix + call['function'] + '('));

        for (var i = 0; i < call['args'].length; i++) {
            if (i != 0)
                result.appendChild(wrap(','));

            result.appendChild(renderAny(call['args'][i], root));
        }

        result.appendChild(wrap(')'));

        return result;
    }

    function renderVariable(name:string):Node {
        function red(v:string) {
            var result = wrap(v);
            result.style.color = '#800';
            return result;
        }

        if (/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/.test(name)) {
            return red('$' + name);
        } else {
            return collect([red('$\{'), renderString(name), red('}')])
        }
    }

    function renderLocals(locals, root):Node {
        return expandable2(bold('local variables'), function () {
            var rows = [];

            if (locals instanceof Array) {
                if (!locals) {
                    rows.push([italics('none')]);
                } else {
                    for (var i = 0; i < locals.length; i++) {
                        var local = locals[i];
                        var name = local['name'];
                        rows.push([
                            renderVariable(name),
                            wrap('='),
                            renderAny(local['value'], root)
                        ]);
                    }
                }
            } else {
                rows.push([italics('n/a')]);
            }

            return createTable(rows);
        }, false);
    }

    function renderGlobals(globals, root) {
        return expandable2(bold('global variables'), function () {
            if (!globals)
                return italics('n/a');

            var staticVariables = globals['staticVariables'];
            var staticProperties = globals['staticProperties'];
            var globalVariables = globals['globalVariables'];
            var rows = [];

            for (var i = 0; i < staticVariables.length; i++) {
                var v = staticVariables[i];
                var pieces = document.createDocumentFragment();
                if (v['class']) {
                    pieces.appendChild(wrap(v['class']));
                    pieces.appendChild(wrap('::'));
                }
                pieces.appendChild(wrap(v['function']));
                pieces.appendChild(wrap('()'));
                pieces.appendChild(wrap('::'));
                pieces.appendChild(keyword('static'));
                pieces.appendChild(renderVariable(v['name']));

                rows.push([ pieces, wrap('='), renderAny(v['value'], root) ]);
            }

            for (var i = 0; i < staticProperties.length; i++) {
                var p = staticProperties[i];
                var pieces = document.createDocumentFragment();
                pieces.appendChild(wrap(p['class']));
                pieces.appendChild(wrap('::'));
                pieces.appendChild(keyword(p['access']));
                pieces.appendChild(keyword('static'));
                pieces.appendChild(renderVariable(p['name']));

                rows.push([pieces, wrap('='), renderAny(p['value'], root)]);
            }

            for (var i = 0; i < globalVariables.length; i++) {
                var pieces = document.createDocumentFragment();
                var v = globalVariables[i];
                var superglobals = ['_SERVER', '_GET', '_POST', '_FILES', '_REQUEST', '_COOKIE', '_ENV', '_SESSION'];
                if (superglobals.indexOf(v['name']) == -1)
                    pieces.appendChild(keyword('global'));
                pieces.appendChild(renderVariable(v['name']));

                rows.push([pieces, wrap('='), renderAny(v['value'], root)]);

            }

            return createTable(rows);
        }, false);
    }

    function renderException(x, root):Node {
        if (!x)
            return italics('none');

        return expandable2(collect([keyword('new'), wrap(x['class'])]), function () {
            var table = createTable([
                [bold('code'), wrap(x['code'])],
                [bold('message'), wrap(x['message'])],
                [bold('location'), renderLocation(x['location'])],
                [bold('previous'), renderException(x['preivous'], root)]
            ]);
            return collect([
                table,
                block(renderLocals(x['locals'], root)),
                block(renderStack(x['stack'], root)),
                block(renderGlobals(x['globals'], root))
            ]);
        });
    }

    function renderLocation(location):Node {
        var wrapper = document.createDocumentFragment();
        var file = location['file'];
        var line = location['line'];
        wrapper.appendChild(wrap(file));
        wrapper.appendChild(renderInt(line));

        return expandable2(wrapper, function () {
            var sourceCode = location['sourceCode'];

            if (!sourceCode)
                return italics('n/a');

            var codeLines = document.createDocumentFragment();

            for (var codeLine in sourceCode) {
                if (!sourceCode.hasOwnProperty(codeLine))
                    continue;

                var lineNumber = document.createElement('span');
                lineNumber.appendChild(document.createTextNode(String(codeLine)));
                lineNumber.style.width = '3em';
                lineNumber.style.borderRightWidth = '0.125em';
                lineNumber.style.borderRightStyle = 'solid';
                lineNumber.style.borderRightColor = 'black';
                lineNumber.style.display = 'inline-block';

                var row = document.createElement('div');
                row.appendChild(lineNumber);
                row.appendChild(document.createTextNode(sourceCode[codeLine]));
                if (codeLine == line)
                    row.style.backgroundColor = '#fbb';

                codeLines.appendChild(row);
            }

            var inner = wrapNode(codeLines, false);
            inner.style.backgroundColor = '#def';
            inner.style.padding = '0.25em';
            return inner;
        });
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
            return renderArray(root, v);
        else if (root[0] === 'unknown')
            return renderUnknown();
        else if (root[0] === 'object')
            return renderObject(root, v);
        else if (root[0] === 'exception')
            return renderException(root[1], v);
        else if (root[0] === 'resource')
            return collect([keyword('resource'), wrap(root[1]['type'])]);
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
            var parsedJSON = JSON.parse(text.value);
            text.value = JSON.stringify(parsedJSON, undefined, 4);
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
