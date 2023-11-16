function reset() {
    location.reload();
}
$(document).ready(function() {
    var url = $('#graph-container').data('url');
    $.get(url, function(data){
        var width = 960,
            height = 600;
        var dataMap = data.reduce(function(map, node) {
            map[node.id] = node;
            return map;
        }, {});

        // create the tree array
        var treeData = [];
        data.forEach(function(node) {
            // add to parent
            var parent = dataMap[node.parent_id];
            if(node.depth == 1){
                treeData.push(node);
            }
            else if (parent) {
                // create child array if it doesn't exist
                if(!parent.children){
                    parent.children = [];
                }
                // add node to child array
                parent.children.push(node);
            } else {
                // parent is null or missing
                treeData.push(node);
            }
        });

        var sTreeData = '{"name": "'+$("div#summary h3:first").text()+'","children": '+JSON.stringify(treeData)+'}';
        treeData = JSON.parse(sTreeData);

        //initialising hierarchical data
        root = d3.hierarchy(treeData);
        //console.log(root);
        var i = 0;

        var transform = d3.zoomIdentity;

        var nodeSvg, linkSvg, simulation, nodeEnter, linkEnter ;

        var svg = d3.select("#graph-container").append("svg")
            .attr("width", width)
            .attr("height", height)
            .call(d3.zoom().scaleExtent([1 / 2, 8]).on("zoom", zoomed))
            .append("g")
            .attr("transform", "translate(40,0)");

        function zoomed() {
            svg.attr("transform", d3.event.transform);
        }

        var attractForce = d3.forceManyBody().strength(50).distanceMax(500)
            .distanceMin(100);
        var collisionForce = d3.forceCollide(30).strength(1).iterations(100);

        simulation = d3.forceSimulation()
            .force("link", d3.forceLink().id(function(d) { return d.id; }).distance(100))
            //.force("charge", d3.forceManyBody())
            .alphaDecay(0.01)
            .force("attractForce",attractForce)
            .force("collisionForce",collisionForce)
            .force("center", d3.forceCenter(width / 2, height / 2))
            .on("tick", ticked);

        update();

        root.children.forEach(toggleAll);

        function update() {
            var nodes = flatten(root);
            var links = root.links();

            nodes[0].x = width / 2;
            nodes[0].y = height / 2;

            //console.log(nodes);
            linkSvg = svg.selectAll(".link")
                .data(links, function(d) { return d.target.id; })

            linkSvg.exit().remove();

            var linkEnter = linkSvg.enter()
                .append("line")
                .attr("class", "link");

            linkSvg = linkEnter.merge(linkSvg)

            nodeSvg = svg.selectAll(".node")
                .data(nodes, function(d) { return d.id; })

            nodeSvg.exit().remove();

            var nodeEnter = nodeSvg.enter()
                .append("g")
                .attr("class", "node")
                .on("click", click)
                .call(d3.drag()
                    .on("start", dragstarted)
                    .on("drag", dragged)
                    .on("end", dragended))

            nodeEnter.append("circle")
                .attr("r", 4  )
                .attr("fill", function(d) { return color(d); })
                .attr("stroke", function(d) { return "darkblue"; })
                .attr("stroke-width", function(d) { return strokeWidth(d); })
                .append("title")
                .text(function(d) { return d.data.name; })

            nodeEnter.append("text")
                .attr("dy", 3)
                .attr("x", function(d) { return d.children ? -8 : 8; })
                .style("text-anchor", function(d) { return d.children ? "end" : "start"; })
                .text(function(d) { return d.data.name; })
                .on("click",function(d){if(d.data.real_id){window.open('/class/' + d.data.real_id + '#graph','_blank')};});

            nodeSvg = nodeEnter.merge(nodeSvg);

            simulation
                .nodes(nodes)

            simulation.force("link")
                .links(links);

        }

        function color(d) {
            var hexaCode;
            //if(d.data.link_type == "parent_class"){
            if(d.data.link_type == "ingoing_property"){
                hexaCode = "#3182bd";
            }
            //else if(d.data.link_type == "child_class"){
            else if(d.data.link_type == "outgoing_property"){
                hexaCode = "#c6dbef";
            }
            //else if(d.data.link_type == "equivalent_class"){
            //    hexaCode = "#2cd66b";
            //}
            else hexaCode = "#fd8d3c";

            return hexaCode;
        }

        function strokeWidth(d) {
            var size;
            if(d.children || d._children){
                size = "1px";
            }
            else size = "0px";
            return size;
        }

        function ticked() {
            linkSvg
                .attr("x1", function(d) { return d.source.x; })
                .attr("y1", function(d) { return d.source.y; })
                .attr("x2", function(d) { return d.target.x; })
                .attr("y2", function(d) { return d.target.y; });

            nodeSvg
                .attr("transform", function(d) { return "translate(" + d.x + ", " + d.y + ")"; });

        }

        function click (d) {
            if (d.children) {
                d._children = d.children;
                d.children = null;
                update();
                simulation.restart();
            } else {
                d.children = d._children;
                d._children = null;
                update();
                simulation.restart();
            }
        }

        function dragstarted(d) {
            if (!d3.event.active) simulation.alphaTarget(0.3).restart();
            d.fx = d.x;
            d.fy = d.y;
        }

        function dragged(d) {
            d.fx = d3.event.x;
            d.fy = d3.event.y;
        }

        function dragended(d) {
            if (!d3.event.active) simulation.alphaTarget(0);
            d.fx = null;
            d.fy = null;
        }

        function toggleAll(d) {
            if (d.children) {
                d.children.forEach(toggleAll);
                if (d.data.depth < 1){
                    return;
                }
                click(d);
            }
        }

        function flatten (root) {
            // hierarchical data to flat data for force layout
            var nodes = [];
            function recurse(node) {
                if (node.children) node.children.forEach(recurse);
                if (!node.id) node.id = ++i;
                else ++i;
                nodes.push(node);
            }
            recurse(root);
            return nodes;
        }

    }, "json");

});
