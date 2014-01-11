///<reference path="jquery.d.ts" />

module PrettyPrinter {

    $(function () {
        var body = document.getElementsByTagName('body')[0];
        var text = document.createElement('textarea');
        body.appendChild(text);
        text.style.width = '800px';
        text.style.height = '500px';
        var container = document.createElement('div');
        container.style.padding = '8px';
        body.appendChild(container);

        function newBox(text:string = ''):HTMLElement {
            var div = document.createElement('div');
            div.style.verticalAlign = 'baseline';
            div.style.display = 'inline-block';
            if (text.length !== 0)
                div.appendChild(document.createTextNode(text));
            return div;
        }

        function newDiv(text:string = ''):HTMLElement {
            var div = document.createElement('div');
            div.style.verticalAlign = 'baseline';
            div.style.display = 'block';
            div.appendChild(document.createTextNode(text));
            return div;
        }

        function expandable(closedContent:() => HTMLElement, openContent:() => HTMLElement):HTMLElement {
            var open = false;
            var toggle = document.createElement('a');
            toggle.href = 'javascript:void(0)';
            toggle.style.verticalAlign = 'text-bottom';
            var container = newBox();

            function refresh() {
                $(toggle).empty().text(open ? '-' : '+');
                $(container).empty();
                container.appendChild(open ? openContent() : closedContent());
            }

            $(toggle).click(function () {
                open = !open;
                refresh();
            });

            refresh();

            var wrapper = newBox();
            wrapper.appendChild(toggle);
            wrapper.appendChild(document.createTextNode(' '));
            wrapper.appendChild(container);

            return wrapper;
        }

        function createTable(data:HTMLElement[][]):HTMLElement {
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

        function bold(content:string):HTMLElement {
            var box = newBox(content);
            box.style.fontWeight = 'bold';
            return box;
        }

        function keyword(word:string) {
            var box = newBox(word);
            box.style.color = '#008';
            box.style.fontWeight = 'bold';
            return box;
        }

        function renderString(x:string):HTMLElement {
            var result = newBox('"');
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

        function renderInt(x:number):HTMLElement {
            var result = newBox(String(x));
            result.style.color = '#00F';
            return result;
        }

        function renderFloat(x:number):HTMLElement {
            return newBox(String(x));
        }

        function renderBool(x:boolean):HTMLElement {
            return keyword(x ? 'true' : 'false');
        }

        function renderNull():HTMLElement {
            return keyword('null');
        }

        function renderArray(x:any):HTMLElement {
            var result = document.createElement('span');
            $(result).text('array...');
            $(result).click(function (eventObject:JQueryEventObject) {
            });
            return result;
        }

        function renderUnknown() {
            var result = document.createElement('span');
            result.appendChild(document.createTextNode('unknown'));
            return result;
        }

        function renderObject(x, root):HTMLElement {
            var result = $(document.createElement('span'));
            result.text('object...');
            return result[0];
        }

        function renderStack(stack:any[], root):HTMLElement {
            var rows = [];

            for (var x = 0; x < stack.length; x++) {
                var location = renderLocation(stack[x]['location']);
                location.appendChild(document.createTextNode(' '));
                var functionCall = renderFunctionCall(stack[x], root);
                rows.push([location, functionCall]);
            }

            return createTable(rows);
        }

        function renderFunctionCall(call:any, root):HTMLElement {
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
                    result.appendChild(newBox(', '));

                result.appendChild(renderAny(call['args'][i], root));
            }

            result.appendChild(newBox(')'));

            return result;
        }

        function renderVariable(name:string):HTMLElement {
            var result = newBox('$' + name);
            result.style.color = '#800';
            return result;
        }

        function renderLocals(locals, root):HTMLElement {
            if (locals instanceof Array) {
                if (!locals)
                    return newBox('none');

                var rows = [];

                for (var i = 0; i < locals.length; i++) {
                    var local = locals[i];
                    var name = local['name'];
                    var value = newBox(' = ');
                    value.appendChild(renderAny(local['value'], root));
                    value.appendChild(document.createTextNode(';'));
                    rows.push([renderVariable(name), value]);
                }

                return createTable(rows);
            } else {
                return newBox('n/a');
            }
        }

        function renderException(x, root):HTMLElement {
            if (!x)
                return newBox('none');

            return createTable([
                [bold('class '), newBox(x['class'])],
                [bold('code '), newBox(x['code'])],
                [bold('message '), newBox(x['message'])],
                [bold('location '), renderLocation(x['location'])],
                [bold('stack '), renderStack(x['stack'], root)],
                [bold('locals '), renderLocals(x['locals'], root)],
                [bold('globals '), newBox('global variables...')],
                [bold('previous '), renderException(x['preivous'], root)]
            ]);
        }

        function renderLocation(location):HTMLElement {
            var wrapper = document.createElement('div');
            wrapper.appendChild(newBox(location['file']));
            wrapper.appendChild(document.createTextNode(':'));
            wrapper.appendChild(renderInt(location['line']));

            function closedContent():HTMLElement {
                return wrapper;
            }

            function openContent():HTMLElement {
                return newBox('open!');
            }

            return expandable(closedContent, openContent);
        }

        function renderAny(root, v):HTMLElement {
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
                return renderObject(root[1], v);
            else if (root[0] === 'exception')
                return renderException(root[1], v);
            else
                throw new String("not goord");
        }

        function renderWhole(v):HTMLElement {
            return renderAny(v['root'], v);
        }

        $(text).change(function (e:JQueryEventObject) {
            var json:string = text.value;
            var parsedJSON = $.parseJSON(json);
            var rendered:HTMLElement = renderWhole(parsedJSON);
            $(container).empty();
            container.appendChild(rendered);
        });

        text.value = "{\"root\":[\"exception\",{\"class\":\"MuhMockException\",\"code\":\"Dummy exception code\",\"message\":\"This is a dummy exception message.\\n\\nlololool\",\"location\":{\"line\":9000,\"file\":\"\\/path\\/to\\/muh\\/file\",\"sourceCode\":null},\"previous\":null,\"stack\":[{\"function\":\"aFunction\",\"class\":\"DummyClass1\",\"isStatic\":null,\"location\":{\"line\":1928,\"file\":\"\\/path\\/to\\/muh\\/file\",\"sourceCode\":null},\"object\":[\"object\",0],\"args\":[[\"object\",1]]},{\"function\":\"aFunction\",\"class\":null,\"isStatic\":null,\"location\":{\"line\":1928,\"file\":\"\\/path\\/to\\/muh\\/file\",\"sourceCode\":null},\"object\":null,\"args\":[[\"object\",2]]}],\"locals\":[{\"name\":\"lol\",\"value\":8},{\"name\":\"foo\",\"value\":\"bar\"}],\"globals\":{\"staticProperties\":[{\"name\":\"blahProperty\",\"value\":null,\"class\":\"BlahClass\",\"access\":\"private\",\"isDefault\":false}],\"globalVariables\":[{\"name\":\"lol global\",\"value\":null},{\"name\":\"blahVariable\",\"value\":null}],\"staticVariables\":[{\"name\":\"public\",\"value\":null,\"class\":null,\"function\":\"BlahAnotherClass\"},{\"name\":\"lolStatic\",\"value\":null,\"class\":\"BlahYetAnotherClass\",\"function\":\"blahMethod\"}]}}],\"arrays\":[],\"objects\":[{\"class\":\"ErrorHandler\\\\DummyClass1\",\"hash\":\"0000000058b5388000000000367cf886\",\"properties\":[{\"name\":\"private1\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass1\",\"access\":\"private\",\"isDefault\":true},{\"name\":\"protected1\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass1\",\"access\":\"protected\",\"isDefault\":true},{\"name\":\"public1\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass1\",\"access\":\"public\",\"isDefault\":true}]},{\"class\":\"ErrorHandler\\\\DummyClass2\",\"hash\":\"0000000058b5388300000000367cf886\",\"properties\":[{\"name\":\"private2\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass2\",\"access\":\"private\",\"isDefault\":true},{\"name\":\"protected2\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass2\",\"access\":\"protected\",\"isDefault\":true},{\"name\":\"public2\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass2\",\"access\":\"public\",\"isDefault\":true},{\"name\":\"private1\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass1\",\"access\":\"private\",\"isDefault\":true},{\"name\":\"protected1\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass1\",\"access\":\"protected\",\"isDefault\":true},{\"name\":\"public1\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass1\",\"access\":\"public\",\"isDefault\":true}]},{\"class\":\"ErrorHandler\\\\DummyClass2\",\"hash\":\"0000000058b5388a00000000367cf886\",\"properties\":[{\"name\":\"private2\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass2\",\"access\":\"private\",\"isDefault\":true},{\"name\":\"protected2\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass2\",\"access\":\"protected\",\"isDefault\":true},{\"name\":\"public2\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass2\",\"access\":\"public\",\"isDefault\":true},{\"name\":\"private1\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass1\",\"access\":\"private\",\"isDefault\":true},{\"name\":\"protected1\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass1\",\"access\":\"protected\",\"isDefault\":true},{\"name\":\"public1\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass1\",\"access\":\"public\",\"isDefault\":true}]}]}";
        $(text).change();
    });
}