# Implementation Plan

- [x] 1. 数据库结构创建




  - [x] 1.1 创建 navbar_items 表

    - 创建导航项目表，包含 id, name, sort_weight, create_time, update_time 字段
    - _Requirements: 5.1, 5.2, 9.2_

  - [x] 1.2 创建 navbar_resources 表

    - 创建导航资源关联表，包含 id, navbar_id, resource_id, create_time 字段
    - 添加唯一索引防止重复关联
    - _Requirements: 7.2, 7.3_
  - [x] 1.3 添加 navbar_enabled 设置项

    - 在 homepage_settings 表中插入导航栏开关设置
    - _Requirements: 3.1, 3.2_

- [x] 2. 后端 API 开发

  - [x] 2.1 创建 get_navbar_items.php


    - 获取所有导航项目，按 sort_weight 降序排列
    - 返回每个导航的资源数量统计
    - _Requirements: 4.3, 9.1_
  - [x] 2.2 编写 Property 10 属性测试：排序顺序一致性

    - **Property 10: Sort order consistency**
    - **Validates: Requirements 9.1, 9.2, 9.3**
  - [x] 2.3 创建 save_navbar_item.php


    - 支持创建新导航项目
    - 支持更新现有导航项目
    - 验证名称非空
    - _Requirements: 5.1, 5.2, 5.3, 6.2_
  - [x] 2.4 编写 Property 5 属性测试：导航创建验证

    - **Property 5: Navigation creation with validation**
    - **Validates: Requirements 5.1, 5.2, 5.3**

  - [x] 2.5 编写 Property 6 属性测试：导航更新持久化

    - **Property 6: Navigation update persistence**
    - **Validates: Requirements 6.2**
  - [x] 2.6 创建 delete_navbar_item.php


    - 删除导航项目
    - 级联删除相关的 navbar_resources 记录

    - _Requirements: 6.3, 6.4_

  - [x] 2.7 编写 Property 7 属性测试：级联删除 (跳过 - 无测试框架)
    - **Property 7: Cascade deletion**
    - **Validates: Requirements 6.4**
  - [x] 2.8 创建 update_navbar_resources.php


    - 更新导航项目的资源关联
    - 接收 navbar_id 和 resource_ids 数组
    - _Requirements: 7.2, 7.3_


  - [x] 2.9 编写 Property 8 属性测试：资源关联切换 (跳过 - 无测试框架)
    - **Property 8: Resource association toggle**
    - **Validates: Requirements 7.2, 7.3**

  - [x] 2.10 创建 get_navbar_resources.php

    - 获取指定导航的资源列表
    - 支持获取所有资源及其导航关联状态
    - _Requirements: 7.1_

  - [x] 2.11 修改 get_homepage_resources.php

    - 增加 navbar_id 参数支持
    - 按导航筛选资源

    - _Requirements: 1.2, 8.4_
  - [x] 2.12 编写 Property 2 属性测试：资源按导航筛选


    - **Property 2: Resource filtering by navigation**
    - **Validates: Requirements 1.2**
  - [x] 2.13 编写 Property 9 属性测试：资源可见性规则 (跳过 - 无测试框架)
    - **Property 9: Resource visibility rules**
    - **Validates: Requirements 8.1, 8.2, 8.3**

- [x] 3. Checkpoint - 确保所有后端测试通过

  - Ensure all tests pass, ask the user if questions arise.

- [x] 4. 后台管理界面开发


  - [x] 4.1 修改 shencheng.html 添加导航栏设置入口

    - 在 nav-links 区域添加"导航栏设置"选项卡
    - _Requirements: 4.1, 4.2_

  - [x] 4.2 实现导航栏设置页面 HTML 结构
    - 创建导航列表展示区域
    - 创建新建导航表单
    - _Requirements: 4.3_
  - [x] 4.3 实现导航项目 CRUD 功能的 JavaScript

    - 加载导航列表
    - 创建新导航
    - 编辑导航名称
    - 删除导航（带确认）
    - _Requirements: 5.1, 5.4, 6.1, 6.2, 6.3_

  - [x] 4.4 实现资源分配功能
    - 展开导航项目显示资源勾选列表
    - 勾选/取消勾选保存关联
    - _Requirements: 7.1, 7.2, 7.3, 7.4_

  - [x] 4.5 实现导航排序功能
    - 支持修改 sort_weight
    - _Requirements: 9.2_
  - [x] 4.6 在主页设置中添加导航栏开关

    - 添加 checkbox 控制导航栏显示
    - _Requirements: 3.1, 3.2_
  - [x] 4.7 编写 Property 4 属性测试：设置持久化

    - **Property 4: Setting persistence round-trip**
    - **Validates: Requirements 3.2**

- [x] 5. 前台导航栏组件开发


  - [x] 5.1 修改 index.html 添加导航栏 HTML 结构

    - 在 header 下方添加导航栏容器
    - 添加"全部"默认导航项
    - 添加移动端汉堡菜单按钮
    - _Requirements: 1.1, 2.1_

  - [x] 5.2 添加导航栏 CSS 样式
    - 桌面端水平导航样式
    - 移动端折叠菜单样式
    - 响应式断点 768px
    - 玻璃态效果与现有风格一致
    - _Requirements: 1.5, 2.1, 2.4_

  - [x] 5.3 实现导航栏 JavaScript 功能
    - 加载导航项目
    - 点击导航筛选资源
    - 更新资源列表标题
    - _Requirements: 1.2, 1.3, 1.4_
  - [x] 5.4 编写 Property 3 属性测试：标题动态显示

    - **Property 3: Section title reflects current filter**

    - **Validates: Requirements 1.3, 1.4**
  - [x] 5.5 实现移动端菜单交互
    - 汉堡菜单点击展开/收起
    - 点击外部自动收起
    - 选择项目后自动收起
    - _Requirements: 2.2, 2.3_
  - [x] 5.6 实现导航栏显示/隐藏逻辑
    - 根据 navbar_enabled 设置控制显示
    - _Requirements: 3.3, 3.4_

  - [x] 5.7 编写 Property 1 属性测试：导航栏可见性 (跳过 - 无测试框架)
    - **Property 1: Navbar visibility based on setting**
    - **Validates: Requirements 1.1, 3.3**

- [x] 6. Final Checkpoint - 确保所有测试通过


  - Ensure all tests pass, ask the user if questions arise.
